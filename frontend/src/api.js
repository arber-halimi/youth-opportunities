const apiOrigin = import.meta.env.VITE_API_ORIGIN || '';
const authPath = '/api/v1/auth';

export class ApiError extends Error {
  constructor(message, status = 0, fields = {}) {
    super(message);
    this.status = status;
    this.fields = fields;
  }
}

async function request(path, { method = 'GET', body, token } = {}) {
  const headers = { Accept: 'application/json' };

  if (body) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = `Bearer ${token}`;

  let response;
  try {
    response = await fetch(`${apiOrigin.replace(/\/$/, '')}${path}`, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined,
      credentials: 'omit',
      redirect: 'error',
      cache: 'no-store',
    });
  } catch {
    throw new ApiError('Nuk u lidhëm me serverin. Provo përsëri.');
  }

  if (response.status === 204) return null;

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    const message = response.status === 429
      ? 'Ke bërë shumë kërkesa. Prit pak dhe provo përsëri.'
      : 'Kërkesa nuk mund të përfundohej. Provo përsëri.';

    throw new ApiError(message, response.status, data?.errors ?? {});
  }

  if (!data) throw new ApiError('Serveri ktheu një përgjigje të papritur.');

  return data;
}

const post = (action, body) => request(`${authPath}/${action}`, { method: 'POST', body });

export const authApi = {
  async login(values) {
    const { result } = await post('login', values);
    if (!result?.access_token || !Number.isFinite(result.expires_in) || result.expires_in <= 0) {
      throw new ApiError('Serveri nuk ktheu një token të vlefshëm.');
    }
    return result;
  },
  register: (values) => post('register', values),
  forgotPassword: (values) => post('forgot-password', values),
  resetPassword: (values) => post('reset-password', values),
  async me(token) {
    const { result } = await request(`${authPath}/me`, { token });
    if (!result?.email) throw new ApiError('Serveri nuk ktheu profilin.');
    return result;
  },
  logout: (token) => request(`${authPath}/logout`, { method: 'POST', token }),
  resendVerification: (token) => request(`${authPath}/email/verification-notification`, {
    method: 'POST',
    token,
  }),
  verifyEmail: (path, token) => request(path, { method: 'POST', token }),
};

export function verificationPath(link) {
  if (!link) return null;

  try {
    const trustedUrl = import.meta.env.VITE_VERIFICATION_ORIGIN
      || apiOrigin
      || (import.meta.env.DEV ? 'http://localhost:8000' : window.location.origin);
    const url = new URL(link);
    if (
      url.origin !== new URL(trustedUrl).origin
      || url.username
      || url.password
      || url.hash
      || !/^\/api\/v1\/auth\/email\/verify\/\d+\/[a-f0-9]{40}$/.test(url.pathname)
      || !url.searchParams.has('signature')
      || !url.searchParams.has('expires')
    ) return null;

    // Laravel signs the relative path and original query string.
    return url.pathname + url.search;
  } catch {
    return null;
  }
}
