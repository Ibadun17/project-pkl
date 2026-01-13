async function apiFetch(path, options = {}) {
  const parts = window.location.pathname.split('/public/');
  const basePath = parts.length > 1 ? parts[0] : '';

  const API_BASE = 'http://10.11.15.188:85/api';
  const url = API_BASE + path;

  const method = options.method || 'GET';
  const headers = Object.assign({ 'Content-Type': 'application/json' }, options.headers || {});
  const body = options.body ? JSON.stringify(options.body) : undefined;

  const res = await fetch(url, {
    method,
    headers,
    body,
    credentials: 'include'
  });

  const text = await res.text();

  let json = null;
  try { json = JSON.parse(text); } catch(e) {}

  if (!res.ok) {
    const msg = json?.message || json?.error || `Request gagal (${res.status})`;
    throw new Error(msg + (json ? '' : ` | Response: ${text.slice(0,150)}`));
  }

  // normal: {success,message,data}
  if (json) return json;

  // fallback kalau server balikin text
  return { success: true, message: 'OK', data: text };
}
