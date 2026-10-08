import { ComponentPropsWithoutRef, forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react'

// Importing the altcha package registers the <altcha-widget> custom element.
import 'altcha'
// Official JSX typings for <altcha-widget> (no custom .d.ts needed).
import type {} from 'altcha/types/react'
import type { WidgetAttributes, WidgetMethods } from 'altcha/types'

export type AltchaHandle = {
    /** Clear the solved state and fetch a fresh challenge. Call after every submit. */
    reset: () => void
}

type Props = Omit<ComponentPropsWithoutRef<'altcha-widget'>, 'challenge'> & {
    /** Endpoint that returns a challenge (route name: altcha.challenge). */
    challengeUrl?: string
    /** Called with the base64 payload once verified, and with '' otherwise. */
    onChange?: (payload: string) => void
}

export const AltchaWidget = forwardRef<AltchaHandle, Props>(function AltchaWidget(
    { challengeUrl = '/altcha/challenge', onChange, ...widgetProps },
    handle,
) {
    const widget = useRef<WidgetAttributes & WidgetMethods & HTMLElement>(null)
    const onChangeRef = useRef(onChange)
    const [mounted, setMounted] = useState(false)
    onChangeRef.current = onChange // keeps the listener stable without stale closures

    useEffect(() => {
        setMounted(true)
    }, [])

    useImperativeHandle(handle, () => ({
        reset: () => {
            // Widget methods only exist after its "load" event, hence the optional call.
            widget.current?.reset?.()
            if (onChangeRef.current) {
                onChangeRef.current('');
            }
        },
    }))

    useEffect(() => {
        const node = widget.current
        if (!node) return

        const onState = (ev: Event) => {
            const detail = (ev as CustomEvent).detail
            if (onChangeRef.current) {
                onChangeRef.current(detail?.state === 'verified' && detail.payload ? detail.payload : '')
            }
        }

        node.addEventListener('statechange', onState)

        // Important with SPA navigation: otherwise the listener outlives the component.
        return () => node.removeEventListener('statechange', onState)
    }, [])

    // v2 widget: the attribute is `challenge` (a URL or challenge data). `challengeurl` was v1.
    // Add other attributes here as needed: auto="onsubmit", type="checkbox", language="de", ...

    // The custom element renders its UI into the light DOM when it upgrades. Mount it
    // only after hydration so React never hydrates against widget-injected children.
    if (!mounted) {
        return null
    }

    return <altcha-widget ref={widget} challenge={challengeUrl} {...widgetProps} />
})

export default AltchaWidget
