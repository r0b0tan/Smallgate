{{-- The large "S" watermark at the foot of the sign-in panel: two interlocking
     hooks, the lower one the upper turned by 180 degrees. Lit from the top
     left -- only the edges facing up carry a highlight, the faces darken
     downwards. Drawn for a panel as wide as the viewBox; it sits on the
     panel's bottom edge and runs off to the right. --}}
<svg {{ $attributes }} viewBox="0 0 247 254" fill="none" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="sg-watermark-face" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#fff" stop-opacity="0.05" />
            <stop offset="1" stop-color="#fff" stop-opacity="0.015" />
        </linearGradient>
    </defs>

    <g fill="url(#sg-watermark-face)">
        <path d="M330 -12.2L115 68.4C79 81.9 64.9 122.2 81 140L122.8 185.9L165.3 170L121.5 121.8Q115.9 115.7 124 112.7L330 35.4Z" />
        <path d="M-9 308.2L206 227.6C242 214.1 256.1 173.8 240 156L198.2 110.1L155.7 126L199.5 174.2Q205.1 180.3 197 183.3L-9 260.6Z" />
    </g>

    <g stroke-width="1" stroke-linejoin="round">
        <path stroke="#fff" stroke-opacity="0.07"
              d="M330 -12.2L115 68.4C79 81.9 64.9 122.2 81 140L122.8 185.9" />
        <path stroke="#fff" stroke-opacity="0.07"
              d="M-9 260.6L197 183.3Q205.1 180.3 199.5 174.2L155.7 126L198.2 110.1" />
        <path stroke="#000" stroke-opacity="0.06"
              d="M330 35.4L124 112.7Q115.9 115.7 121.5 121.8L165.3 170L122.8 185.9" />
        <path stroke="#000" stroke-opacity="0.06"
              d="M-9 308.2L206 227.6C242 214.1 256.1 173.8 240 156L198.2 110.1" />
    </g>
</svg>
