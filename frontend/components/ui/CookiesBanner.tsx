'use client';

import { useEffect, useState } from 'react';
import { Button } from './Button';

export function CookiesBanner({ fixed = true }: { fixed?: boolean }) {
    const [show, setShow] = useState(false);

    useEffect(() => {
        // localStorage is unavailable during SSR, so the real value can only be
        // read after mount — starting hidden and revealing here (rather than in
        // a lazy useState initializer) avoids a server/client hydration mismatch.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setShow(!localStorage.getItem('cookies-consent'));
    }, []);

    function respond(consent: 'accepted' | 'rejected') {
        localStorage.setItem('cookies-consent', consent);
        setShow(false);
    }

    if (!show) return null;

    return (
        <div
            className={`${fixed ? 'fixed inset-x-0 bottom-0 z-50 ' : ''}rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card`}
        >
            <div className="mx-auto flex max-w-4xl flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-body-sm text-txt-black-700">
                    We use cookies to improve your experience on this site. By continuing to browse, you agree to our
                    use of cookies.
                </p>
                <div className="flex flex-shrink-0 gap-2">
                    <Button variant="secondary" size="sm" type="button" onClick={() => respond('rejected')}>
                        Reject
                    </Button>
                    <Button variant="primary" size="sm" type="button" onClick={() => respond('accepted')}>
                        Accept
                    </Button>
                </div>
            </div>
        </div>
    );
}
