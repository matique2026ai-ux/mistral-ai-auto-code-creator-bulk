import Link from 'next/link';
import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { useRouter } from 'next/router';

const Navbar = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const router = useRouter();

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 50);
    };

    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  return (
    <nav className={`fixed top-0 w-full z-50 transition-all ${scrolled ? 'bg-primary shadow-lg' : 'bg-transparent'}`} role="navigation" aria-label="Main Navigation">
      <div className="container mx-auto px-4">
        <div className="flex justify-between items-center py-4">
          <Link href="/" className="text-2xl font-bold text-secondary">Le Céleste</Link>
          <button
            onClick={() => setIsOpen(!isOpen)}
            className="md:hidden text-text-primary"
            aria-expanded={isOpen}
            aria-label="Toggle menu"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              {isOpen ? <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /> : <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />}
            </svg>
          </button>
          <div className="hidden md:flex space-x-8">
            <Link href="/menu" className="hover:text-secondary transition">Menu</Link>
            <Link href="/reservations" className="hover:text-secondary transition">Réservations</Link>
            <Link href="/chef" className="hover:text-secondary transition">Notre Chef</Link>
            <Link href="/gallery" className="hover:text-secondary transition">Galerie</Link>
            <Link href="/contact" className="hover:text-secondary transition">Contact</Link>
          </div>
        </div>
      </div>
      <AnimatePresence>
        {isOpen && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            className="md:hidden overflow-hidden"
          >
            <div className="flex flex-col space-y-4 py-4">
              <Link href="/menu" className="hover:text-secondary transition" onClick={() => setIsOpen(false)}>Menu</Link>
              <Link href="/reservations" className="hover:text-secondary transition" onClick={() => setIsOpen(false)}>Réservations</Link>
              <Link href="/chef" className="hover:text-secondary transition" onClick={() => setIsOpen(false)}>Notre Chef</Link>
              <Link href="/gallery" className="hover:text-secondary transition" onClick={() => setIsOpen(false)}>Galerie</Link>
              <Link href="/contact" className="hover:text-secondary transition" onClick={() => setIsOpen(false)}>Contact</Link>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </nav>
  );
};

export default Navbar;