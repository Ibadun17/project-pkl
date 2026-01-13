const API_ORIGIN = "http://10.11.15.188:85";
const API_PREFIX = "/pkl-project/api"; // <-- ganti kalau nama folder project beda

const API_BASE = 'http://10.11.15.188:85/pkl-project/api';

function buildUrl(path) {
  if (!path.startsWith("/")) path = "/" + path;
  return API_BASE + path;
}

async function readResponse(res) {
  const ct = res.headers.get("content-type") || "";
  const text = await res.text();
  if (ct.includes("application/json")) {
    try { return { kind: "json", value: JSON.parse(text) }; } catch {}
  }
  try { return { kind: "json", value: JSON.parse(text) }; } catch {}
  return { kind: "text", value: text };
}

export async function apiFetch(path, options = {}) {
  const method = (options.method || "GET").toUpperCase();
  const headers = { ...(options.headers || {}) };

  let body;
  if (options.body !== undefined && options.body !== null) {
    body = JSON.stringify(options.body);
    if (!headers["Content-Type"]) headers["Content-Type"] = "application/json";
  }

  const res = await fetch(buildUrl(path), {
    method,
    headers,
    body,
    credentials: "include", // penting: session cookie PHP ikut
  });

  const parsed = await readResponse(res);

  if (!res.ok) {
    const msg =
      parsed.kind === "json"
        ? (parsed.value?.message || parsed.value?.error || `Request gagal (${res.status})`)
        : `Request gagal (${res.status}) | ${String(parsed.value).slice(0, 200)}`;
    throw new Error(msg);
  }

  if (parsed.kind === "json") return parsed.value;
  return { success: true, message: "OK", data: parsed.value };
}
