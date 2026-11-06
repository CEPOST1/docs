<svg class="svg-defs" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="g-brand" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#25f4ee"/><stop offset=".55" stop-color="#7c5cff"/><stop offset="1" stop-color="#fe2c55"/>
    </linearGradient>
    <!-- Liquid-glass refraction (used as backdrop-filter in Chromium browsers) -->
    <filter id="lg-filter" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">
      <feTurbulence type="fractalNoise" baseFrequency="0.006 0.009" numOctaves="2" seed="11" result="noise"/>
      <feGaussianBlur in="noise" stdDeviation="2.5" result="soft"/>
      <feDisplacementMap in="SourceGraphic" in2="soft" scale="38" xChannelSelector="R" yChannelSelector="G"/>
    </filter>
  </defs>
  <symbol id="i-logo" viewBox="0 0 32 32"><circle cx="14" cy="14" r="10" fill="none" stroke="url(#g-brand)" stroke-width="3.2"/><path d="M11.5 9.8v8.4l7-4.2z" fill="url(#g-brand)" stroke="none"/><path d="M21.5 21.5 28 28" stroke="url(#g-brand)" stroke-width="3.6" stroke-linecap="round"/></symbol>
  <g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5"/><path d="M4 19h16"/></symbol>
    <symbol id="i-stories" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 1-6.4 2.6" stroke-dasharray="3 2.4"/><circle cx="12" cy="12" r="5.2"/><path d="m11 10 2.6 2-2.6 2z" fill="currentColor"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 10h17M8 3v4M16 3v4"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></symbol>
    <symbol id="i-book" viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/></symbol>
    <symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/></symbol>
    <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
    <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-5-5 5 5-5 5"/></symbol>
    <symbol id="i-copy" viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6.5A2.5 2.5 0 0 0 13.5 4h-7A2.5 2.5 0 0 0 4 6.5v7A2.5 2.5 0 0 0 6.5 16H8"/></symbol>
    <symbol id="i-share" viewBox="0 0 24 24"><path d="M12 15V3m0 0L8 7m4-4 4 4"/><path d="M5 12v6.5A2.5 2.5 0 0 0 7.5 21h9a2.5 2.5 0 0 0 2.5-2.5V12"/></symbol>
    <symbol id="i-external" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v4.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6H10"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.3a4.3 4.3 0 0 1 7.5 2.5C19.5 15.4 12 20 12 20z"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-comment" viewBox="0 0 24 24"><path d="M20.5 11.5a8.5 8 0 0 1-12.6 7L3.5 20l1.4-3.9A8.5 8 0 1 1 20.5 11.5z"/></symbol>
    <symbol id="i-play" viewBox="0 0 24 24"><path d="M8 5.5v13l10.5-6.5z" fill="currentColor"/></symbol>
    <symbol id="i-pin" viewBox="0 0 24 24"><path d="M9 4h6l-1 6 3.5 3.5h-11L10 10z"/><path d="M12 13.5V20"/></symbol>
    <symbol id="i-music" viewBox="0 0 24 24"><path d="M9 18V5.5l11-2V16"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="16" r="2.5"/></symbol>
    <symbol id="i-image" viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="3"/><circle cx="9" cy="10" r="1.8"/><path d="m4 17 5-4.5 4 3.5 3-2.5 4.5 3.5"/></symbol>
    <symbol id="i-video" viewBox="0 0 24 24"><rect x="3" y="5.5" width="13" height="13" rx="3"/><path d="m16 10.5 5-3v9l-5-3"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.3 7.5 9.5 4.3-1.2 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-zap" viewBox="0 0 24 24"><path d="M13 2.5 4.5 13.5H12l-1 8 8.5-11H12z"/></symbol>
    <symbol id="i-sparkles" viewBox="0 0 24 24"><path d="M12 3.5 13.8 9 19.5 11 13.8 13 12 18.5 10.2 13 4.5 11 10.2 9z"/><path d="M19 3v3M17.5 4.5h3M5 17.5v3M3.5 19h3"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
    <symbol id="i-link" viewBox="0 0 24 24"><path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/></symbol>
    <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.6v.2"/></symbol>
    <symbol id="i-edit" viewBox="0 0 24 24"><path d="M4 20h4L19.5 8.5a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/></symbol>
    <symbol id="i-hash" viewBox="0 0 24 24"><path d="M5 9h15M4 15h15M10 3.5 8 20.5M16 3.5l-2 17"/></symbol>
    <symbol id="i-flag" viewBox="0 0 24 24"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></symbol>
  </g>
  <symbol id="i-verified" viewBox="0 0 24 24"><path fill="#20d5ec" stroke="none" d="M12 1.8l2.6 1.9 3.2-.1 1 3 2.6 1.9-1 3.1 1 3-2.6 1.9-1 3.1-3.2-.1L12 22.2l-2.6-1.9-3.2.1-1-3.1-2.6-1.9 1-3-1-3.1 2.6-1.9 1-3 3.2.1z"/><path d="m8 12.2 2.7 2.7L16.3 9.3" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
</svg>
