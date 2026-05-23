import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import Navbar from '../components/Navbar';
import Footer from '../components/Footer';
import ReservationForm from '../components/ReservationForm';

const ReservationsPage = () => {
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
  }, []);

  if (!isClient) {
    return (
      <div className="min-h-screen bg-bg-dark flex items-center justify-center">
        <motion.div
          animate={{ rotate: 360 }}
          transition={{ duration: 1, repeat: Infinity, ease: "linear" }}
          className="w-12 h-12 border-4 border-secondary border-t-transparent rounded-full"
        />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-bg-dark text-text-primary">
      <Navbar />
      
      <main className="container mx-auto px-4 py-16 md:py-24">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          className="max-w-4xl mx-auto"
        >
          <h1 className="text-4xl md:text-5xl font-bold text-center mb-4">
            Réservez votre expérience
          </h1>
          <p className="text-center text-text-secondary mb-12 max-w-2xl mx-auto">
            Plongez dans un voyage culinaire inoubliable au Céleste. Notre équipe se tient prête à vous offrir une soirée exceptionnelle.
          </p>
          
          <div className="bg-primary/30 backdrop-blur-custom rounded-xl p-8 md:p-12 border border-white/10">
            <ReservationForm />
          </div>
        </motion.div>
      </main>
      
      <Footer />
    </div>
  );
};

export default ReservationsPage;
