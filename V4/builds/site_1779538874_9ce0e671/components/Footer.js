import React from 'react';
import Link from 'next/link';
import { motion } from 'framer-motion';
import { FaFacebookF, FaInstagram, FaTwitter, FaYoutube } from 'react-icons/fa';

const Footer = () => {
  return (
    <footer className="bg-primary text-text-secondary py-16">
      <div className="container mx-auto px-4">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
          {/* Restaurant Info */}
          <div className="space-y-4">
            <h3 className="text-2xl font-headings font-bold text-white">Le Céleste</h3>
            <p className="text-sm leading-relaxed">
              Une expérience gastronomique céleste au cœur de la ville. Découvrez des saveurs uniques dans un cadre raffiné.
            </p>
            <div className="flex space-x-4 pt-4">
              <motion.a
                href="#"
                className="text-text-secondary hover:text-secondary transition-colors"
                whileHover={{ scale: 1.2 }}
                whileTap={{ scale: 0.9 }}
              >
                <FaFacebookF size={20} />
              </motion.a>
              <motion.a
                href="#"
                className="text-text-secondary hover:text-secondary transition-colors"
                whileHover={{ scale: 1.2 }}
                whileTap={{ scale: 0.9 }}
              >
                <FaInstagram size={20} />
              </motion.a>
              <motion.a
                href="#"
                className="text-text-secondary hover:text-secondary transition-colors"
                whileHover={{ scale: 1.2 }}
                whileTap={{ scale: 0.9 }}
              >
                <FaTwitter size={20} />
              </motion.a>
              <motion.a
                href="#"
                className="text-text-secondary hover:text-secondary transition-colors"
                whileHover={{ scale: 1.2 }}
                whileTap={{ scale: 0.9 }}
              >
                <FaYoutube size={20} />
              </motion.a>
            </div>
          </div>

          {/* Horaires */}
          <div>
            <h4 className="text-lg font-headings font-semibold text-white mb-4">Horaires</h4>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span>Lundi - Vendredi</span>
                <span>12:00 - 14:30</span>
              </div>
              <div className="flex justify-between">
                <span></span>
                <span>19:00 - 22:30</span>
              </div>
              <div className="flex justify-between">
                <span>Samedi</span>
                <span>19:00 - 23:00</span>
              </div>
              <div className="flex justify-between">
                <span>Dimanche</span>
                <span>Fermé</span>
              </div>
            </div>
          </div>

          {/* Liens utiles */}
          <div>
            <h4 className="text-lg font-headings font-semibold text-white mb-4">Liens utiles</h4>
            <ul className="space-y-2 text-sm">
              <li>
                <Link href="/menu" className="hover:text-secondary transition-colors">
                  Notre Menu
                </Link>
              </li>
              <li>
                <Link href="/reservations" className="hover:text-secondary transition-colors">
                  Réservations
                </Link>
              </li>
              <li>
                <Link href="/gallery" className="hover:text-secondary transition-colors">
                  Galerie
                </Link>
              </li>
              <li>
                <Link href="/chef" className="hover:text-secondary transition-colors">
                  Notre Chef
                </Link>
              </li>
              <li>
                <Link href="/contact" className="hover:text-secondary transition-colors">
                  Contact
                </Link>
              </li>
            </ul>
          </div>

          {/* Newsletter */}
          <div>
            <h4 className="text-lg font-headings font-semibold text-white mb-4">Newsletter</h4>
            <p className="text-sm mb-4">Abonnez-vous pour recevoir nos dernières nouvelles et offres spéciales.</p>
            <form className="space-y-3">
              <input
                type="email"
                placeholder="Votre email"
                className="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-md focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-sm"
              />
              <motion.button
                type="submit"
                className="w-full bg-secondary text-primary py-2 px-4 rounded-md font-medium hover:bg-secondary/90 transition-colors"
                whileHover={{ scale: 1.02 }}
                whileTap={{ scale: 0.98 }}
              >
                S'abonner
              </motion.button>
            </form>
          </div>
        </div>

        {/* Copyright */}
        <div className="border-t border-gray-800 pt-8 text-center text-sm text-text-secondary">
          <p>&copy; {new Date().getFullYear()} Le Céleste. Tous droits réservés.</p>
          <div className="mt-2 space-x-4">
            <Link href="/privacy" className="hover:text-secondary transition-colors">
              Politique de confidentialité
            </Link>
            <Link href="/terms" className="hover:text-secondary transition-colors">
              Conditions d'utilisation
            </Link>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;