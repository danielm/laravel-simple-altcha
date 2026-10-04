import 'react';

// Lets TypeScript accept the <altcha-widget> custom element in JSX.
declare module 'react' {
    namespace JSX {
        interface IntrinsicElements {
            'altcha-widget': React.DetailedHTMLProps<React.HTMLAttributes<HTMLElement>, HTMLElement> & {
                challengeurl?: string;
                [attribute: string]: unknown;
            };
        }
    }
}
