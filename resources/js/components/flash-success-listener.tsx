import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

export function FlashSuccessListener() {
    const page = usePage<{ flash?: { success?: string | null } }>();
    const lastMessage = useRef<string | null>(null);

    useEffect(() => {
        const message = page.props.flash?.success ?? null;

        if (message && message !== lastMessage.current) {
            toast.success(message);
            lastMessage.current = message;
        }
    }, [page.props.flash?.success]);

    return null;
}
