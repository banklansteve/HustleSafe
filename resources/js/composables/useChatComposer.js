/**
 * Enter sends; Shift+Enter inserts a newline.
 */
export function useChatComposer(sendFn) {
    function onComposerKeydown(event) {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        sendFn();
    }

    return { onComposerKeydown };
}
