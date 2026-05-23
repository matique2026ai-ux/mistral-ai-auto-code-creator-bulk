import { signIn, signOut, useSession } from 'next-auth/react';
import { useRouter } from 'next/navigation';

/**
 * Authentification utilisateur
 * @param {string} email - Email de l'utilisateur
 * @param {string} password - Mot de passe de l'utilisateur
 * @returns {Promise<{success: boolean, error?: string}>}
 */
export const login = async (email, password) => {
  try {
    const result = await signIn('credentials', {
      redirect: false,
      email,
      password,
    });

    if (result?.error) {
      return { success: false, error: result.error };
    }

    return { success: true };
  } catch (error) {
    console.error('Login error:', error);
    return { success: false, error: 'Une erreur est survenue' };
  }
};

/**
 * Déconnexion utilisateur
 */
export const logout = async () => {
  try {
    await signOut({ redirect: false });
    return { success: true };
  } catch (error) {
    console.error('Logout error:', error);
    return { success: false, error: 'Une erreur est survenue' };
  }
};

/**
 * Hook personnalisé pour gérer la session utilisateur
 * @returns {{ session: any, status: string, isLoading: boolean, isAuthenticated: boolean }}
 */
export const useAuth = () => {
  const { data: session, status } = useSession();
  const router = useRouter();

  const isLoading = status === 'loading';
  const isAuthenticated = status === 'authenticated';

  const requireAuth = (callback) => {
    if (!isAuthenticated && !isLoading) {
      router.push('/auth/login');
      return false;
    }
    return true;
  };

  return {
    session,
    status,
    isLoading,
    isAuthenticated,
    requireAuth,
  };
};

/**
 * Vérification du rôle utilisateur
 * @param {string} requiredRole - Rôle requis
 * @returns {boolean}
 */
export const checkUserRole = (session, requiredRole) => {
  if (!session?.user?.role) return false;
  return session.user.role === requiredRole;
};

/**
 * Récupération du token JWT
 * @returns {string|null}
 */
export const getAuthToken = () => {
  if (typeof window !== 'undefined') {
    return localStorage.getItem('authToken');
  }
  return null;
};

/**
 * Configuration des headers d'authentification
 * @returns {Object}
 */
export const getAuthHeaders = () => {
  const token = getAuthToken();
  return {
    headers: {
      'Content-Type': 'application/json',
      ...(token && { Authorization: `Bearer ${token}` }),
    },
  };
};