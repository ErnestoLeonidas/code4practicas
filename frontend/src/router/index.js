import { createRouter, createWebHistory } from 'vue-router'
import Home from '../views/Home.vue'
import Login from '../views/Login.vue'
import Usuarios from '../views/Usuarios.vue'
import CambiarPassword from '../views/CambiarPassword.vue'
import RecuperarPassword from '../views/RecuperarPassword.vue'
import RestablecerPassword from '../views/RestablecerPassword.vue'
import Estudiantes from '../views/Estudiantes.vue'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/login', name: 'login', component: Login, meta: { publico: true } },
  { path: '/recuperar-password', name: 'recuperar-password', component: RecuperarPassword, meta: { publico: true } },
  { path: '/restablecer', name: 'restablecer', component: RestablecerPassword, meta: { publico: true } },
  { path: '/', name: 'home', component: Home, meta: { requiereAuth: true } },
  { path: '/estudiantes', name: 'estudiantes', component: Estudiantes, meta: { requiereAuth: true } },
  {
    path: '/usuarios',
    name: 'usuarios',
    component: Usuarios,
    meta: { requiereAuth: true, soloAdmin: true },
  },
  {
    path: '/cambiar-password',
    name: 'cambiar-password',
    component: CambiarPassword,
    meta: { requiereAuth: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.inicializado) await auth.cargarSesion()

  // 1. Requiere sesión y no la hay: al login (recordando el destino).
  if (to.meta.requiereAuth && !auth.autenticado) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  // 2. Cambio de contraseña obligatorio antes de cualquier otra vista privada.
  if (auth.autenticado && auth.debeCambiarPassword && !to.meta.publico && to.name !== 'cambiar-password') {
    return { name: 'cambiar-password' }
  }
  // 3. Ruta sólo para admin.
  if (to.meta.soloAdmin && !auth.esAdmin) {
    return { path: '/' }
  }
  // 4. Ya autenticado: no tiene sentido ver el login.
  if (to.name === 'login' && auth.autenticado) {
    return { path: '/' }
  }
  return true
})

export default router
