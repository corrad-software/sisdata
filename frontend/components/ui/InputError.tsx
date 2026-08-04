export function InputError({ messages, className }: { messages?: string[]; className?: string }) {
    if (!messages || messages.length === 0) return null;

    return (
        <ul className={`text-body-xs text-danger-600 space-y-1 ${className ?? ''}`}>
            {messages.map((message) => (
                <li key={message}>{message}</li>
            ))}
        </ul>
    );
}
