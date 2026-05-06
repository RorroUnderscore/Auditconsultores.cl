import { useState } from 'react';

export function Login({ onLogin }: { onLogin: () => void }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  return <div>
    <h1>Acceso administrador</h1>
    <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder='Correo' />
    <input value={password} onChange={(e) => setPassword(e.target.value)} type='password' placeholder='Contraseña' />
    <button onClick={onLogin} disabled={!email || !password}>Ingresar</button>
  </div>;
}
