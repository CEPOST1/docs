/* TokLens browser check: proof-of-work (SHA-256) + optional Cloudflare Turnstile. */
(() => {
  'use strict';
  const box = document.getElementById('guard');
  if (!box) return;
  const challenge = box.dataset.c || '';
  const bits = Math.min(30, Math.max(8, Number(box.dataset.bits) || 17));
  const tsKey = box.dataset.tsKey || '';
  const failEl = box.querySelector('[data-guard-fail]');

  // ---- compact synchronous SHA-256 (ASCII input) returning the first 32-bit word
  const K = new Uint32Array([
    0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
    0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
    0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
    0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
    0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
    0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
    0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
    0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
  ]);
  const W = new Uint32Array(64);
  function sha256First(msg) {
    const l = msg.length;
    const words = new Uint32Array(((l + 9 + 63) >> 6) << 4);
    for (let i = 0; i < l; i++) words[i >> 2] |= msg.charCodeAt(i) << (24 - (i & 3) * 8);
    words[l >> 2] |= 0x80 << (24 - (l & 3) * 8);
    words[words.length - 1] = l * 8;
    let h0 = 0x6a09e667, h1 = 0xbb67ae85, h2 = 0x3c6ef372, h3 = 0xa54ff53a;
    let h4 = 0x510e527f, h5 = 0x9b05688c, h6 = 0x1f83d9ab, h7 = 0x5be0cd19;
    for (let j = 0; j < words.length; j += 16) {
      for (let i = 0; i < 16; i++) W[i] = words[j + i];
      for (let i = 16; i < 64; i++) {
        const x = W[i - 15], y = W[i - 2];
        const s0 = ((x >>> 7) | (x << 25)) ^ ((x >>> 18) | (x << 14)) ^ (x >>> 3);
        const s1 = ((y >>> 17) | (y << 15)) ^ ((y >>> 19) | (y << 13)) ^ (y >>> 10);
        W[i] = (W[i - 16] + s0 + W[i - 7] + s1) | 0;
      }
      let a = h0, b = h1, c = h2, d = h3, e = h4, f = h5, g = h6, h = h7;
      for (let i = 0; i < 64; i++) {
        const S1 = ((e >>> 6) | (e << 26)) ^ ((e >>> 11) | (e << 21)) ^ ((e >>> 25) | (e << 7));
        const t1 = (h + S1 + ((e & f) ^ (~e & g)) + K[i] + W[i]) | 0;
        const S0 = ((a >>> 2) | (a << 30)) ^ ((a >>> 13) | (a << 19)) ^ ((a >>> 22) | (a << 10));
        const t2 = (S0 + ((a & b) ^ (a & c) ^ (b & c))) | 0;
        h = g; g = f; f = e; e = (d + t1) | 0; d = c; c = b; b = a; a = (t1 + t2) | 0;
      }
      h0 = (h0 + a) | 0; h1 = (h1 + b) | 0; h2 = (h2 + c) | 0; h3 = (h3 + d) | 0;
      h4 = (h4 + e) | 0; h5 = (h5 + f) | 0; h6 = (h6 + g) | 0; h7 = (h7 + h) | 0;
    }
    return h0 >>> 0;
  }
  // exposed for tests only
  window.__tlSha256First = sha256First;

  function solve() {
    const shift = 32 - bits;
    let n = 0;
    return new Promise((resolve) => {
      const step = () => {
        const end = n + 4000;
        for (; n < end; n++) {
          if ((sha256First(challenge + ':' + n) >>> shift) === 0) return resolve(String(n));
        }
        setTimeout(step, 0);
      };
      step();
    });
  }

  function turnstileToken() {
    if (!tsKey) return Promise.resolve('');
    return new Promise((resolve) => {
      const start = Date.now();
      (function wait() {
        if (window.turnstile) {
          window.turnstile.render('#ts-box', { sitekey: tsKey, callback: resolve, 'error-callback': () => resolve('') });
        } else if (Date.now() - start < 15000) {
          setTimeout(wait, 100);
        } else {
          resolve('');
        }
      })();
    });
  }

  const fail = () => { box.classList.add('is-failed'); if (failEl) failEl.hidden = false; };

  Promise.all([solve(), turnstileToken()]).then(([n, ts]) => fetch('/api/verify', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'toklens' },
    body: JSON.stringify({
      c: challenge,
      n,
      ts,
      wd: navigator.webdriver === true,
      tz: (() => { try { return Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (_) { return ''; } })(),
    }),
  })).then((r) => {
    if (r.ok) { box.classList.add('is-done'); location.reload(); } else { fail(); }
  }).catch(fail);
})();
