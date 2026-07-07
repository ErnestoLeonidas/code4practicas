Puerto y comandos de desarrollo
===============================

Puertos elegidos (poco comunes) para evitar conflictos con otras apps:

- Backend (PHP dev server): `18081`
- Frontend (Vite): `51731`

Comandos rápidos (desde la raíz del repo):

```bash
# Inicia backend PHP (en primer plano o en background)
php -S localhost:18081 -t api

# En otra terminal: iniciar frontend (Vite)
cd frontend
npm install   # sólo la primera vez
npm run dev
```

Nota:
- Asegúrate de que `api/config.php` tiene `app_url` apuntando a `http://localhost:51731`.
- `frontend/vite.config.js` está configurado para proxy `/api` a `http://127.0.0.1:18081`.
