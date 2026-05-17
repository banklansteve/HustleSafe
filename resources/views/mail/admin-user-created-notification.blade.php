<p>Hello {{ $createdBy->name }},</p>

<p>Your super admin account created a new user account on HustleSafe.</p>

<ul>
    <li><strong>Name:</strong> {{ $createdUser->name }}</li>
    <li><strong>Email:</strong> {{ $createdUser->email }}</li>
    <li><strong>Role:</strong> {{ $createdUser->role?->name ?? $createdUser->account_type }}</li>
    <li><strong>Created at:</strong> {{ $createdUser->created_at?->toDayDateTimeString() }}</li>
</ul>

<p><strong>Audit reason:</strong> {{ $auditReason }}</p>

<p>If you did not perform this action, secure your account immediately and review recent admin activity.</p>
