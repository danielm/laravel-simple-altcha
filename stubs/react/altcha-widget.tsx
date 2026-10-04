import 'altcha';
import {
    forwardRef,
    useEffect,
    useImperativeHandle,
    useRef,
    type HTMLAttributes,
} from 'react';

export type AltchaHandle = {
    /** Clear the solved state and fetch a fresh challenge. Call after every submit. */
    reset: () => void;
};

type Props = Omit<HTMLAttributes<HTMLElement>, 'onChange'> & {
    /** Endpoint that returns a challenge (route name: altcha.challenge). */
    challengeUrl?: string;
    /** Called with the base64 payload once verified, and with '' otherwise. */
    onChange: (payload: string) => void;
    /** Any other <altcha-widget> string attribute, e.g. auto="onsubmit", type="checkbox", language="de". */
    [attribute: string]: unknown;
};

export const AltchaWidget = forwardRef<AltchaHandle, Props>(function AltchaWidget(
    { challengeUrl = '/altcha/challenge', onChange, ...rest },
    handle,
) {
    const el = useRef<any>(null);
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange; // keeps the listener stable without stale closures

    useImperativeHandle(handle, () => ({
        reset: () => {
            el.current?.reset?.();
            onChangeRef.current('');
        },
    }));

    useEffect(() => {
        const node = el.current;
        if (!node) return;

        const onState = (ev: Event) => {
            const detail = (ev as CustomEvent).detail;
            onChangeRef.current(detail?.state === 'verified' && detail.payload ? detail.payload : '');
        };

        node.addEventListener('statechange', onState);

        // Important with SPA navigation: otherwise the widget keeps working after unmount.
        return () => node.removeEventListener('statechange', onState);
    }, []);

    return <altcha-widget ref={el} challengeurl={challengeUrl} {...(rest as object)} />;
});

export default AltchaWidget;
