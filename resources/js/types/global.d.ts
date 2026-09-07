import type { SharedPageProps } from './index';

declare module '@inertiajs/core' {
    interface PageProps extends SharedPageProps {}
}

declare module '*.module.css';

declare module '*.png' {
    const src: string;
    export default src;
}

declare module '*.jpg' {
    const src: string;
    export default src;
}

declare module '*.svg' {
    const src: string;
    export default src;
}
