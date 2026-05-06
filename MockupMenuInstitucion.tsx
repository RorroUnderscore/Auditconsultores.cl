import { useMemo, useState } from 'react';
import { seedLikertQuestions } from './src/data/mockDb';
import { Login } from './src/pages/Login';
import { AdminDashboard } from './src/pages/AdminDashboard';
import { PublicSurveyByToken } from './src/pages/PublicSurveyByToken';

seedLikertQuestions();

export default function AuditConsultoresSystemApp() {
  const [isAuth, setIsAuth] = useState(false);
  const route = useMemo(() => {
    const path = window.location.pathname;
    if (path.startsWith('/survey/')) return { page: 'survey', token: path.replace('/survey/', '') } as const;
    if (path === '/admin' || path === '/login') return { page: 'admin' } as const;
    return { page: 'landing-help' } as const;
  }, []);

  if (route.page === 'survey') return <PublicSurveyByToken token={route.token} />;
  if (route.page === 'admin' && !isAuth) return <Login onLogin={() => setIsAuth(true)} />;
  if (route.page === 'admin' && isAuth) return <AdminDashboard />;

  return <div>Usa <code>/admin</code> para el panel y <code>/survey/demo-docente-token</code> para encuesta pública.</div>;
}
