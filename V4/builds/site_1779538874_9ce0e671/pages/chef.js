import Head from 'next/head';
import Navbar from '../components/Navbar';
import ChefBio from '../components/ChefBio';
import Footer from '../components/Footer';
import { motion } from 'framer-motion';

export default function ChefPage() {
  return (
    <>
      <Head>
        <title>Notre Chef - Le Céleste</title>
        <meta name="description" content="Découvrez le chef derrière l'expérience gastronomique du restaurant Le Céleste" />
        <meta property="og:title" content="Notre Chef - Le Céleste" />
        <meta property="og:description" content="Découvrez le chef derrière l'expérience gastronomique du restaurant Le Céleste" />
        <meta property="og:type" content="website" />
      </Head>

      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 0.6 }}
        className="min-h-screen bg-bg-dark text-text-primary"
      >
        <Navbar />

        <main className="container mx-auto px-4 py-16 md:py-24">
          <ChefBio />
        </main>

        <Footer />
      </motion.div>
    </>
  );
}