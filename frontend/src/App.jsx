import { useEffect, useRef, useState } from 'react';
import { Link, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { ApiError, authApi, verificationPath } from './api.js';

const fields = {
  name: { label: 'Emri i plotë', autoComplete: 'name', maxLength: 255 },
  email: { label: 'Adresa e emailit', type: 'email', autoComplete: 'email', maxLength: 255 },
  password: { label: 'Fjalëkalimi', type: 'password', autoComplete: 'current-password' },
  password_confirmation: { label: 'Konfirmo fjalëkalimin', type: 'password', autoComplete: 'new-password' },
};

function Field({ name, error, newPassword, value }) {
  const [visible, setVisible] = useState(false);
  const field = fields[name];
  const isPassword = field.type === 'password';

  return (
    <div className="field">
      <label htmlFor={name}>{field.label}</label>
      <div className="input-wrap">
        <input
          id={name}
          name={name}
          type={visible ? 'text' : field.type || 'text'}
          autoComplete={newPassword && isPassword ? 'new-password' : field.autoComplete}
          maxLength={field.maxLength}
          minLength={newPassword && isPassword ? 8 : undefined}
          defaultValue={value}
          readOnly={value !== undefined}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? `${name}-error` : undefined}
          required
        />
        {isPassword && (
          <button
            className="show-password"
            type="button"
            aria-controls={name}
            aria-label={`${visible ? 'Fshih' : 'Shfaq'} ${field.label.toLowerCase()}`}
            onClick={() => setVisible(!visible)}
          >
            {visible ? 'Fshih' : 'Shfaq'}
          </button>
        )}
      </div>
      {error && <p className="field-error" id={`${name}-error`}>{error}</p>}
    </div>
  );
}

function Notice({ children, error = false }) {
  if (!children) return null;
  return <p className={`notice ${error ? 'notice-error' : ''}`} role={error ? 'alert' : 'status'}>{children}</p>;
}

function useAction() {
  const active = useRef(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  async function run(action) {
    if (active.current) return;
    active.current = true;
    setBusy(true);
    setError(null);
    try {
      await action();
    } catch (failure) {
      setError(failure);
    } finally {
      active.current = false;
      setBusy(false);
    }
  }

  return { busy, error, run };
}

function AuthForm({ title, description, names, submit, onSubmit, children, newPassword, email, notice, login }) {
  const { busy, error, run } = useAction();

  function handleSubmit(event) {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(event.currentTarget));
    run(async () => {
      if (newPassword && values.password !== values.password_confirmation) {
        throw new ApiError('Kontrollo konfirmimin e fjalëkalimit.', 422, {
          password_confirmation: ['Fjalëkalimet duhet të përputhen.'],
        });
      }
      await onSubmit(values);
    });
  }

  const message = login && error?.status === 401
    ? 'Emaili ose fjalëkalimi është i pasaktë.'
    : error?.status === 422
      ? (error.fields?.token ? 'Linku i rikuperimit është i pavlefshëm ose ka skaduar.' : 'Kontrollo fushat e shënuara.')
      : error?.message;

  return (
    <>
      <h1 tabIndex={-1}>{title}</h1>
      <p className="description">{description}</p>
      <Notice>{notice}</Notice>
      <Notice error>{message}</Notice>
      <form onSubmit={handleSubmit} aria-busy={busy}>
        {names.map((name) => (
          <Field
            key={name}
            name={name}
            error={error?.fields?.[name]?.[0]}
            newPassword={newPassword}
            value={name === 'email' ? email : undefined}
          />
        ))}
        {children}
        <button className="button primary" disabled={busy} type="submit">
          {busy ? 'Po përpunohet…' : submit}
        </button>
      </form>
    </>
  );
}

function loginDestination(state) {
  const destination = state?.returnTo;
  return typeof destination === 'string' && destination.startsWith('/verify-email?')
    ? destination
    : '/account';
}

function Login({ onLogin, notice }) {
  const location = useLocation();
  const navigate = useNavigate();

  async function login(values) {
    await onLogin(values);
    navigate(loginDestination(location.state), { replace: true });
  }

  return (
    <>
      <AuthForm
        title="Kyçu në llogari"
        description="Shkruaj emailin dhe fjalëkalimin për të vazhduar."
        names={['email', 'password']}
        submit="Kyçu"
        onSubmit={login}
        notice={location.state?.notice || notice}
        login
      >
        <Link className="forgot" to="/forgot-password">Ke harruar fjalëkalimin?</Link>
      </AuthForm>
      <p className="form-footer">S’ke llogari? <Link to="/register">Regjistrohu</Link></p>
    </>
  );
}

function Register() {
  const navigate = useNavigate();

  return (
    <>
      <AuthForm
        title="Krijo llogarinë"
        description="Regjistrohu me një email ku mund ta marrësh linkun e verifikimit."
        names={['name', 'email', 'password', 'password_confirmation']}
        submit="Krijo llogari"
        newPassword
        onSubmit={async (values) => {
          await authApi.register(values);
          navigate('/login', {
            replace: true,
            state: { notice: 'Llogaria u krijua. Kontrollo emailin për linkun e verifikimit dhe kyçu.' },
          });
        }}
      />
      <p className="form-footer">Ke llogari? <Link to="/login">Kyçu</Link></p>
    </>
  );
}

function ForgotPassword() {
  const [sent, setSent] = useState(false);

  if (sent) {
    return (
      <>
        <h1 tabIndex={-1}>Kontrollo emailin</h1>
        <p className="description">Nëse emaili i përket një llogarie, do të marrësh një link për të vendosur fjalëkalim të ri.</p>
        <Link className="button primary" to="/login">Kthehu te kyçja</Link>
      </>
    );
  }

  return (
    <>
      <AuthForm
        title="Rikupero fjalëkalimin"
        description="Shkruaj emailin që ke përdorur për llogarinë."
        names={['email']}
        submit="Dërgo linkun"
        onSubmit={async (values) => {
          await authApi.forgotPassword(values);
          setSent(true);
        }}
      />
      <p className="form-footer"><Link to="/login">Kthehu te kyçja</Link></p>
    </>
  );
}

function ResetPassword({ onReset }) {
  const { search } = useLocation();
  const navigate = useNavigate();
  const params = new URLSearchParams(search);
  const email = params.get('email');
  const token = params.get('token');

  if (!email || !token) {
    return (
      <>
        <h1 tabIndex={-1}>Linku është i paplotë</h1>
        <p className="description">Kërko një link të ri për rikuperimin e fjalëkalimit.</p>
        <Link className="button primary" to="/forgot-password" replace>Kërko link të ri</Link>
      </>
    );
  }

  return (
    <>
      <AuthForm
        title="Fjalëkalim i ri"
        description="Zgjidh një fjalëkalim me të paktën 8 karaktere."
        names={['email', 'password', 'password_confirmation']}
        email={email}
        submit="Ruaj fjalëkalimin"
        newPassword
        onSubmit={async (values) => {
          await authApi.resetPassword({ ...values, email, token });
          onReset();
          navigate('/login', { replace: true });
        }}
      />
      <p className="form-footer"><Link to="/forgot-password" replace>Kërko link të ri</Link></p>
    </>
  );
}

function VerifyEmail({ session, protectedAction }) {
  const location = useLocation();
  const navigate = useNavigate();
  const { busy, error, run } = useAction();
  const link = new URLSearchParams(location.search).get('verification_url');
  const path = verificationPath(link);

  async function verify() {
    await protectedAction(async (token) => {
      await authApi.verifyEmail(path, token);
    }, { refreshUser: true });
    navigate('/account', { replace: true, state: { notice: 'Emaili u verifikua.' } });
  }

  return (
    <>
      <h1 tabIndex={-1}>Verifiko emailin</h1>
      {!path ? (
        <>
          <p className="description">Ky link është i paplotë ose i pavlefshëm. Nga llogaria mund të kërkosh një link të ri.</p>
          <Link className="button primary" to="/account" replace>Shko te llogaria</Link>
        </>
      ) : !session ? (
        <>
          <p className="description">Kyçu në llogarinë që i përket këtij emaili për të vazhduar.</p>
          <Link className="button primary" to="/login" replace state={{ returnTo: location.pathname + location.search }}>
            Kyçu për të vazhduar
          </Link>
        </>
      ) : (
        <>
          <p className="description">Je kyçur si <strong>{session.user.email}</strong>. Konfirmo për të verifikuar adresën.</p>
          <Notice error>{error?.status === 403
            ? 'Linku ka skaduar ose nuk i përket kësaj llogarie.'
            : error?.message}</Notice>
          <button className="button primary" disabled={busy} onClick={() => run(verify)}>
            {busy ? 'Po verifikohet…' : 'Verifiko emailin'}
          </button>
          <p className="form-footer"><Link to="/account" replace>Kthehu te llogaria</Link></p>
        </>
      )}
    </>
  );
}

function Account({ session, protectedAction, onLogout }) {
  const location = useLocation();
  const [notice, setNotice] = useState(location.state?.notice);
  const { busy, error, run } = useAction();
  const { user } = session;

  async function resend() {
    setNotice(null);
    await protectedAction(authApi.resendVerification);
    setNotice('Linku i verifikimit u kërkua. Kontrollo emailin.');
  }

  return (
    <>
      <h1 tabIndex={-1}>Llogaria ime</h1>
      <dl className="profile">
        <div><dt>Emri</dt><dd>{user.name}</dd></div>
        <div><dt>Emaili</dt><dd>{user.email}</dd></div>
        <div>
          <dt>Verifikimi</dt>
          <dd className={user.email_verified_at ? 'verified' : 'unverified'}>
            {user.email_verified_at ? 'Email i verifikuar' : 'Email i paverifikuar'}
          </dd>
        </div>
      </dl>
      <Notice>{notice}</Notice>
      <Notice error>{error?.message}</Notice>
      <div className="account-actions">
        {!user.email_verified_at && (
          <button className="button primary" disabled={busy} onClick={() => run(resend)}>Ridërgo linkun e verifikimit</button>
        )}
        <button className="button secondary" disabled={busy} onClick={() => run(onLogout)}>Dil nga llogaria</button>
      </div>
    </>
  );
}

export default function App() {
  const [session, setSession] = useState(null);
  const [notice, setNotice] = useState(null);
  const location = useLocation();

  useEffect(() => {
    if (!session) return;
    const timeout = window.setTimeout(() => {
      setSession(null);
      setNotice('Kyçja ka skaduar. Kyçu përsëri.');
    }, Math.max(0, session.expiresAt - Date.now()));
    return () => window.clearTimeout(timeout);
  }, [session]);

  useEffect(() => {
    document.querySelector('main h1')?.focus();
  }, [location.pathname]);

  async function login(credentials) {
    const result = await authApi.login(credentials);
    const expiresAt = Date.now() + result.expires_in * 1000;
    const user = await authApi.me(result.access_token);
    setSession({ token: result.access_token, expiresAt, user });
    setNotice(null);
  }

  async function protectedAction(action, { refreshUser = false } = {}) {
    try {
      const result = await action(session.token);
      if (refreshUser) {
        const user = await authApi.me(session.token);
        setSession((current) => current?.token === session.token ? { ...current, user } : current);
      }
      return result;
    } catch (error) {
      if (error.status === 401) {
        setSession(null);
        setNotice('Kyçja ka skaduar. Kyçu përsëri.');
      }
      throw error;
    }
  }

  async function logout() {
    try {
      await authApi.logout(session.token);
    } catch (error) {
      if (error.status !== 401) throw error;
    }
    setSession(null);
    setNotice('Dole nga llogaria.');
  }

  function finishPasswordReset() {
    setSession(null);
    setNotice('Fjalëkalimi u ndryshua. Kyçu me fjalëkalimin e ri.');
  }

  return (
    <div className="layout">
      <aside className="brand">
        <Link className="wordmark" to="/">
          <span className="brand-mark" aria-hidden="true"><i /><i /><i /><i /></span>
          Mundësi për Rininë
        </Link>
        <div className="brand-content">
          <p>Mundësitë fillojnë <em>këtu.</em></p>
        </div>
        <div className="brand-decoration" aria-hidden="true"><span /><span /><span /><span /></div>
      </aside>
      <main className="workspace">
        <div className="panel">
          <Routes>
            <Route path="/" element={<Navigate to={session ? '/account' : '/login'} replace />} />
            <Route path="/login" element={session
              ? <Navigate to={loginDestination(location.state)} replace />
              : <Login onLogin={login} notice={notice} />} />
            <Route path="/register" element={session ? <Navigate to="/account" replace /> : <Register />} />
            <Route path="/forgot-password" element={<ForgotPassword />} />
            <Route path="/reset-password" element={<ResetPassword key={location.search} onReset={finishPasswordReset} />} />
            <Route path="/verify-email" element={<VerifyEmail session={session} protectedAction={protectedAction} />} />
            <Route path="/account" element={session
              ? <Account session={session} protectedAction={protectedAction} onLogout={logout} />
              : <Navigate to="/login" replace />} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </div>
      </main>
    </div>
  );
}
