import { SVGAttributes } from 'react';

export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect width="48" height="48" rx="14" fill="currentColor" />
            <path
                d="M13 33V25M24 33V19M35 33V13"
                stroke="white"
                strokeWidth="3"
                strokeLinecap="round"
            />
            <path
                d="M12 17.5L20.5 12L27 15.5L36 9.5"
                stroke="#6EE7B7"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx="12" cy="17.5" r="2" fill="#6EE7B7" />
            <circle cx="20.5" cy="12" r="2" fill="#6EE7B7" />
            <circle cx="27" cy="15.5" r="2" fill="#6EE7B7" />
            <circle cx="36" cy="9.5" r="2" fill="#6EE7B7" />
        </svg>
    );
}
