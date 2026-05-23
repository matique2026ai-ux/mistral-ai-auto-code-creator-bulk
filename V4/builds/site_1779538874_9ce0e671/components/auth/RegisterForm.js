import { useState } from 'react';
import { useRouter } from 'next/router';
import { motion } from 'framer-motion';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';

const registerSchema = z.object({
  email: z.string().email('Adresse email invalide'),
  password: z.string().min(6, 'Le mot de passe doit contenir au moins 6 caractères'),
  confirmPassword: z.string()
}).refine((data) => data.password === data.confirmPassword, {
  message: "Les mots de passe ne correspondent pas",
  path: ["confirmPassword"],
});

export default function RegisterForm() {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const router = useRouter();
  
  const { register, handleSubmit, formState: { errors } } = useForm({
    resolver: zodResolver(registerSchema)
  });

  const onSubmit = async (data) => {
    setIsLoading(true);
    setError('');
    
    try {
      const response = await fetch('/api/auth/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          email: data.email,
          password: data.password,
        }),
      });
      
      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || 'Erreur lors de l\'inscription');
      }
      
      const result = await response.json();
      console.log('Inscription réussie:', result);
      
      router.push('/login');
    } catch (err) {
      setError(err.message || 'Une erreur est survenue');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5 }}
      className="max-w-md w-full mx-auto p-8 bg-black bg-opacity-50 rounded-xl shadow-lg backdrop-blur-custom border border-white border-opacity-10"
    >
      <h2 className="text-3xl font-bold text-center mb-8 text-secondary">Créer un compte</h2>
      
      {error && (
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          className="mb-4 p-3 bg-red-500 bg-opacity-20 text-error rounded-md text-center"
        >
          {error}
        </motion.div>
      )}
      
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        <div>
          <label htmlFor="email" className="block text-sm font-medium text-text-secondary mb-2">
            Adresse Email
          </label>
          <input
            id="email"
            type="email"
            {...register('email')}
            className={`w-full px-4 py-3 rounded-md bg-black bg-opacity-30 border ${
              errors.email ? 'border-error' : 'border-white border-opacity-20'
            } focus:ring-2 focus:ring-secondary focus:border-transparent outline-none transition-all`}
            placeholder="votre@email.com"
          />
          {errors.email && (
            <p className="mt-1 text-xs text-error">{errors.email.message}</p>
          )}
        </div>
        
        <div>
          <label htmlFor="password" className="block text-sm font-medium text-text-secondary mb-2">
            Mot de passe
          </label>
          <input
            id="password"
            type="password"
            {...register('password')}
            className={`w-full px-4 py-3 rounded-md bg-black bg-opacity-30 border ${
              errors.password ? 'border-error' : 'border-white border-opacity-20'
            } focus:ring-2 focus:ring-secondary focus:border-transparent outline-none transition-all`}
            placeholder="••••••••"
          />
          {errors.password && (
            <p className="mt-1 text-xs text-error">{errors.password.message}</p>
          )}
        </div>
        
        <div>
          <label htmlFor="confirmPassword" className="block text-sm font-medium text-text-secondary mb-2">
            Confirmer le mot de passe
          </label>
          <input
            id="confirmPassword"
            type="password"
            {...register('confirmPassword')}
            className={`w-full px-4 py-3 rounded-md bg-black bg-opacity-30 border ${
              errors.confirmPassword ? 'border-error' : 'border-white border-opacity-20'
            } focus:ring-2 focus:ring-secondary focus:border-transparent outline-none transition-all`}
            placeholder="••••••••"
          />
          {errors.confirmPassword && (
            <p className="mt-1 text-xs text-error">{errors.confirmPassword.message}</p>
          )}
        </div>
        
        <motion.button
          whileTap={{ scale: 0.95 }}
          type="submit"
          disabled={isLoading}
          className={`w-full py-3 px-6 rounded-md text-white font-semibold transition-all duration-300 ${
            isLoading 
              ? 'bg-secondary bg-opacity-70 cursor-not-allowed'
              : 'bg-secondary hover:bg-opacity-90 focus:ring-2 focus:ring-secondary focus:ring-opacity-50'
          }`}
        >
          {isLoading ? (
            <span className="flex items-center justify-center">
              <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Création en cours...
            </span>
          ) : (
            'Créer mon compte'
          )}
        </motion.button>
      </form>
      
      <div className="mt-6 text-center">
        <p className="text-sm text-text-secondary">
          Vous avez déjà un compte ?{' '}
          <button
            onClick={() => router.push('/login')}
            className="text-secondary hover:text-opacity-80 font-medium transition-colors"
          >
            Connectez-vous
          </button>
        </p>
      </div>
    </motion.div>
  );
}