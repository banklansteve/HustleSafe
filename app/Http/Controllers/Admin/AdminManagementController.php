<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyAdminRecordRequest;
use App\Http\Requests\Admin\StoreAdminRecordRequest;
use App\Http\Requests\Admin\SuspendAdminUserRequest;
use App\Http\Requests\Admin\UpdateConversationThreadVisibilityRequest;
use App\Http\Requests\Admin\UpdateAdminRecordRequest;
use App\Mail\AdminStaffInvitationMail;
use App\Mail\AdminUserCreatedNotificationMail;
use App\Models\QuestConversationThread;
use App\Services\Admin\AdminManagementService;
use App\Services\Admin\AdminActivityFeedService;
use App\Services\AdminActivityLogger;
use App\Support\Admin\AdminDateTimeFormatter;
use App\Support\Admin\AdminManagementRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class AdminManagementController extends Controller
{
    public function __construct(
        private AdminManagementService $management,
        private AdminActivityLogger $audit,
        private AdminActivityFeedService $feed,
    ) {}

    public function index(Request $request): Response
    {
        $groups = AdminManagementRegistry::groupedForUi();
        $resourceKey = (string) $request->query('resource', $groups[0]['resources'][0]['key'] ?? 'users');

        if (! isset(AdminManagementRegistry::resources()[$resourceKey])) {
            $resourceKey = array_key_first(AdminManagementRegistry::resources()) ?: 'users';
        }

        $definition = AdminManagementRegistry::resource($resourceKey);
        $perPage = min(50, max(10, (int) $request->input('per_page', 20)));
        $search = trim((string) $request->input('q', ''));

        $records = $this->management->paginate($resourceKey, $perPage, $search !== '' ? $search : null);

        $fields = [];
        foreach (AdminManagementRegistry::fieldSchema($resourceKey) as $name => $schema) {
            $fields[$name] = array_merge($schema, [
                'label' => $schema['label'] ?? str_replace('_', ' ', str_ends_with($name, '_id') ? substr($name, 0, -3) : $name),
                'type' => $schema['type'] ?? 'text',
            ]);

            unset($fields[$name]['rules']);

            if (($schema['type'] ?? null) === 'relation') {
                $fields[$name]['options'] = $this->relationOptions((string) $schema['relation_resource']);
            }
        }

        return Inertia::render('Admin/Management/Index', [
            'resource_key' => $resourceKey,
            'resource_groups' => $groups,
            'definition' => [
                'label' => $definition['label'],
                'description' => $definition['description'] ?? '',
                'creatable' => (bool) ($definition['creatable'] ?? false),
                'editable' => (bool) ($definition['editable'] ?? true),
                'deletable' => (bool) ($definition['deletable'] ?? true),
                'list_columns' => AdminManagementRegistry::listColumns($resourceKey),
                'create_fields' => AdminManagementRegistry::createFields($resourceKey),
                'edit_fields' => AdminManagementRegistry::editFields($resourceKey),
                'fields' => $fields,
                'actions' => $definition['actions'] ?? [],
            ],
            'records' => $records,
            'filters' => ['q' => $search, 'per_page' => $perPage],
        ]);
    }

    public function store(StoreAdminRecordRequest $request, string $resource): RedirectResponse
    {
        $definition = AdminManagementRegistry::resource($resource);
        $payload = $request->except('audit_reason');
        $model = $this->management->create($resource, $payload);

        $this->audit->log(
            $request->user(),
            'admin.management.created',
            AdminManagementRegistry::modelClass($resource),
            (int) $model->getKey(),
            [
                'resource' => $resource,
                'reason' => $request->validated('audit_reason'),
                'payload' => $payload,
            ],
            $request,
        );

        if ($resource === 'users') {
            $model->loadMissing('role');
            $actor = $request->user();
            $auditReason = (string) $request->validated('audit_reason');

            app()->terminating(function () use ($model, $actor, $auditReason): void {
                $this->sendCreatedUserNotifications($model, $actor, $auditReason);
            });
        }

        return back()->with('success', ($definition['label'] ?? 'Record').' created.');
    }

    private function isCreatedAdminUser($user): bool
    {
        return in_array($user->role?->slug, ['admin', 'super_admin'], true)
            || $user->account_type === 'admin';
    }

    private function sendCreatedUserNotifications($model, $actor, string $auditReason): void
    {
        try {
            if ($this->isCreatedAdminUser($model)) {
                Mail::to($model->email)->send(new AdminStaffInvitationMail(
                    $model,
                    $actor,
                    URL::temporarySignedRoute('admin.invitation.show', now()->addDays(7), ['user' => $model->id]),
                ));
            } else {
                $model->sendEmailVerificationNotification();
            }

            Mail::to($actor->email)->send(new AdminUserCreatedNotificationMail(
                $model,
                $actor,
                $auditReason,
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }

        try {
            $this->feed->record(
                'users',
                'user.created_by_admin',
                'User account created',
                "{$actor->name} created {$model->name}",
                $this->feed->entities([
                    ['type' => 'user', 'id' => $model->id, 'label' => $model->name],
                ]),
                ['email' => $model->email, 'role' => $model->role?->slug],
                null,
                $actor,
                $model::class,
                (int) $model->getKey(),
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function update(UpdateAdminRecordRequest $request, string $resource, int $record): RedirectResponse
    {
        $definition = AdminManagementRegistry::resource($resource);
        $before = AdminManagementRegistry::modelClass($resource)::query()->findOrFail($record);
        $editable = AdminManagementRegistry::editFields($resource);
        $payload = $request->only($editable);

        $model = $this->management->update($resource, $record, $payload);

        $this->audit->log(
            $request->user(),
            'admin.management.updated',
            AdminManagementRegistry::modelClass($resource),
            (int) $model->getKey(),
            [
                'resource' => $resource,
                'reason' => $request->validated('audit_reason'),
                'before' => $before->only($editable),
                'after' => $model->only($editable),
            ],
            $request,
        );

        if ($resource === 'users') {
            $this->feed->record(
                'users',
                'user.updated_by_admin',
                'User account updated',
                "{$request->user()->name} updated {$model->name}",
                $this->feed->entities([
                    ['type' => 'user', 'id' => $model->id, 'label' => $model->name],
                ]),
                ['email' => $model->email, 'role' => $model->role?->slug],
                null,
                $request->user(),
                $model::class,
                (int) $model->getKey(),
            );
        }

        return back()->with('success', ($definition['label'] ?? 'Record').' updated.');
    }

    public function destroy(DestroyAdminRecordRequest $request, string $resource, int $record): RedirectResponse
    {
        $definition = AdminManagementRegistry::resource($resource);
        $snapshot = AdminManagementRegistry::modelClass($resource)::query()->findOrFail($record);
        $columns = AdminManagementRegistry::listColumns($resource);

        $this->management->delete($resource, $record);

        $this->audit->log(
            $request->user(),
            'admin.management.deleted',
            AdminManagementRegistry::modelClass($resource),
            $record,
            [
                'resource' => $resource,
                'reason' => $request->validated('audit_reason'),
                'snapshot' => $snapshot->only($columns),
            ],
            $request,
        );

        if ($resource === 'users') {
            $name = (string) ($snapshot->getAttribute('name') ?? ('User #'.$record));
            $this->feed->record(
                'users',
                'user.deleted',
                'User account deleted',
                "{$request->user()->name} deleted {$name}",
                [],
                ['resource' => $resource, 'snapshot' => $snapshot->only($columns)],
                null,
                $request->user(),
                $snapshot::class,
                $record,
                severity: 'warning',
            );
        }

        return back()->with('success', ($definition['label'] ?? 'Record').' deleted.');
    }

    public function show(string $resource, int $record): Response
    {
        $definition = AdminManagementRegistry::resource($resource);
        $modelClass = AdminManagementRegistry::modelClass($resource);
        $with = $definition['with'] ?? [];

        $model = $modelClass::query()
            ->with(is_array($with) ? $with : [])
            ->findOrFail($record);

        $model->loadMissing($this->fileRelationsFor($resource));

        return Inertia::render('Admin/Management/Show', [
            'resource_key' => $resource,
            'definition' => [
                'label' => $definition['label'],
                'description' => $definition['description'] ?? '',
                'editable' => (bool) ($definition['editable'] ?? true),
                'deletable' => (bool) ($definition['deletable'] ?? true),
                'list_columns' => AdminManagementRegistry::listColumns($resource),
                'detail_columns' => $this->detailColumns($model),
                'edit_fields' => AdminManagementRegistry::editFields($resource),
                'fields' => $this->fieldsFor($resource),
            ],
            'record' => $this->management->serializeDetailRow($resource, $model),
            'files' => $this->filePayload($resource, $model),
        ]);
    }

    public function suspend(SuspendAdminUserRequest $request, int $user): RedirectResponse
    {
        $suspend = (bool) $request->validated('suspend');
        $model = $this->management->suspendUser($user, $suspend);

        $this->audit->log(
            $request->user(),
            $suspend ? 'admin.user.suspended' : 'admin.user.unsuspended',
            $model::class,
            (int) $model->id,
            [
                'reason' => $request->validated('audit_reason'),
                'email' => $model->email,
            ],
            $request,
        );

        $this->feed->record(
            'users',
            $suspend ? 'user.suspended' : 'user.reactivated',
            $suspend ? 'User account suspended' : 'User account reactivated',
            $suspend ? "{$model->name} was suspended" : "{$model->name} was reactivated",
            $this->feed->entities([
                ['type' => 'user', 'id' => $model->id, 'label' => $model->name],
            ]),
            ['email' => $model->email, 'reason' => $request->validated('audit_reason')],
            null,
            $request->user(),
            $model::class,
            (int) $model->id,
            severity: $suspend ? 'warning' : 'success',
        );

        return back()->with('success', $suspend ? 'User suspended.' : 'User reinstated.');
    }

    public function showConversationThread(QuestConversationThread $thread): Response
    {
        $thread->load([
            'quest:id,title,reference_code',
            'client:id,name,email',
            'freelancer:id,name,email',
            'messages' => fn ($q) => $q->with('user:id,name,email')->orderBy('created_at'),
            'adminVisibilityChangedBy:id,name,email',
        ]);

        $messages = $thread->messages->map(fn ($message) => [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'author' => $message->user?->name ?? 'Unknown',
            'email' => $message->user?->email,
            'body' => $message->body,
            'created_at' => AdminDateTimeFormatter::formatValue($message->created_at, 'created_at'),
        ]);

        return Inertia::render('Admin/Management/ConversationThread', [
            'thread' => [
                'id' => $thread->id,
                'quest' => $thread->quest?->only(['id', 'title', 'reference_code']),
                'client' => $thread->client?->only(['id', 'name', 'email']),
                'freelancer' => $thread->freelancer?->only(['id', 'name', 'email']),
                'messages_count' => $thread->messages_count,
                'last_message_at' => AdminDateTimeFormatter::formatValue($thread->last_message_at, 'last_message_at'),
                'admin_hidden_at' => AdminDateTimeFormatter::formatValue($thread->admin_hidden_at, 'admin_hidden_at'),
                'admin_deleted_at' => AdminDateTimeFormatter::formatValue($thread->admin_deleted_at, 'admin_deleted_at'),
                'admin_visibility_reason' => $thread->admin_visibility_reason,
                'admin_visibility_changed_by' => $thread->adminVisibilityChangedBy?->only(['id', 'name', 'email']),
                'is_blocked' => $thread->isBlockedByAdmin(),
            ],
            'messages' => $messages,
        ]);
    }

    public function updateConversationThreadVisibility(UpdateConversationThreadVisibilityRequest $request, QuestConversationThread $thread): RedirectResponse
    {
        $data = $request->validated();
        $action = $data['action'];
        $reason = $data['reason'] ?? null;

        if ($action === 'restore') {
            $thread->forceFill([
                'admin_hidden_at' => null,
                'admin_deleted_at' => null,
                'admin_visibility_reason' => null,
                'admin_visibility_changed_by' => $request->user()->id,
            ])->save();
        } else {
            $thread->forceFill([
                'admin_hidden_at' => now(),
                'admin_deleted_at' => $action === 'delete' ? now() : null,
                'admin_visibility_reason' => $reason,
                'admin_visibility_changed_by' => $request->user()->id,
            ])->save();
        }

        $this->audit->log(
            $request->user(),
            'admin.conversation_thread.'.$action,
            QuestConversationThread::class,
            (int) $thread->id,
            ['reason' => $reason, 'quest_id' => $thread->quest_id],
            $request,
        );

        return back()->with('success', $action === 'restore' ? 'Conversation thread restored.' : 'Conversation thread access updated.');
    }

    /**
     * @return list<array{value: int|string, label: string}>
     */
    private function relationOptions(string $resource): array
    {
        if (! isset(AdminManagementRegistry::resources()[$resource])) {
            return [];
        }

        $modelClass = AdminManagementRegistry::modelClass($resource);

        return $modelClass::query()
            ->limit(250)
            ->get()
            ->map(fn ($model) => [
                'value' => $model->getKey(),
                'label' => $this->relationLabel($model),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsFor(string $resourceKey): array
    {
        $fields = [];
        foreach (AdminManagementRegistry::fieldSchema($resourceKey) as $name => $schema) {
            $fields[$name] = array_merge($schema, [
                'label' => $schema['label'] ?? str_replace('_', ' ', str_ends_with($name, '_id') ? substr($name, 0, -3) : $name),
                'type' => $schema['type'] ?? 'text',
            ]);

            unset($fields[$name]['rules']);

            if (($schema['type'] ?? null) === 'relation') {
                $fields[$name]['options'] = $this->relationOptions((string) $schema['relation_resource']);
            }
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function detailColumns($model): array
    {
        return collect(array_keys($model->getAttributes()))
            ->reject(fn (string $column) => in_array($column, ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'], true))
            ->values()
            ->all();
    }

    private function relationLabel($model): string
    {
        $parts = [];
        foreach (['name', 'title', 'reference_code', 'email', 'slug'] as $field) {
            $value = $model->getAttribute($field);
            if (is_string($value) && $value !== '') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? '#'.$model->getKey() : implode(' · ', array_unique($parts));
    }

    /**
     * @return list<string>
     */
    private function fileRelationsFor(string $resource): array
    {
        return match ($resource) {
            'quests', 'portfolios' => ['files'],
            'reviews' => ['attachments'],
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filePayload(string $resource, $model): array
    {
        $files = match ($resource) {
            'quests' => $model->files ?? collect(),
            'portfolios' => $model->files ?? collect(),
            'reviews' => $model->attachments ?? collect(),
            default => collect(),
        };

        $fileResource = match ($resource) {
            'quests' => 'quest_files',
            'portfolios' => 'portfolio_files',
            'reviews' => 'review_attachments',
            default => null,
        };

        return $files->map(fn ($file) => [
            'id' => $file->id,
            'resource' => $fileResource,
            'name' => $file->original_name ?? 'Attachment #'.$file->id,
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
            'url' => method_exists($file, 'url') ? $file->url() : '',
            'is_image' => method_exists($file, 'isImage') ? $file->isImage() : str_starts_with((string) $file->mime_type, 'image/'),
        ])->values()->all();
    }
}
