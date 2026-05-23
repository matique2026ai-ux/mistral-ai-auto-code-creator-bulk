import React from 'react';
import { motion } from 'framer-motion';
import { FaPhoneAlt, FaEnvelope, FaMapMarkerAlt, FaClock } from 'react-icons/fa';

const ContactInfo = () => {
  const contactItems = [
    {
      icon: <FaPhoneAlt className="text-secondary text-2xl" />,
      title: "Téléphone",
      info: "+33 1 23 45 67 89",
      link: "tel:+33123456789"
    },
    {
      icon: <FaEnvelope className="text-secondary text-2xl" />,
      title: "Email",
      info: "contact@leceleste.fr",
      link: "mailto:contact@leceleste.fr"
    },
    {
      icon: <FaMapMarkerAlt className="text-secondary text-2xl" />,
      title: "Adresse",
      info: "12 Rue de la Paix, 75002 Paris, France",
      link: "https://maps.google.com/?q=12+Rue+de+la+Paix,+75002+Paris,+France"
    },
    {
      icon: <FaClock className="text-secondary text-2xl" />,
      title: "Horaires",
      info: "Mar - Sam: 19h - 23h\nDim: 12h - 15h, 19h - 23h\nLun: Fermé",
      link: null
    }
  ];

  return (
    <div className="container mx-auto px-4 py-16">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.6 }}
        className="text-center mb-12"
      >
        <h2 className="text-4xl md:text-5xl font-bold mb-4">Nous Contacter</h2>
        <p className="text-xl text-text-secondary max-w-2xl mx-auto">
          Nous serions ravis de vous accueillir au Céleste. Voici comment nous joindre.
        </p>
      </motion.div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        {contactItems.map((item, index) => (
          <motion.div
            key={index}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: index * 0.1 }}
            className="bg-primary/30 backdrop-blur-custom border border-white/10 rounded-xl p-8 text-center hover:scale-105 transition-transform duration-300"
          >
            <div className="mb-6 flex justify-center">
              {item.icon}
            </div>
            <h3 className="text-xl font-semibold mb-3">{item.title}</h3>
            {item.link ? (
              <a
                href={item.link}
                target={item.link.startsWith('http') ? '_blank' : '_self'}
                rel={item.link.startsWith('http') ? 'noopener noreferrer' : ''}
                className="text-text-secondary hover:text-secondary transition-colors duration-300 block whitespace-pre-line"
              >
                {item.info}
              </a>
            ) : (
              <p className="text-text-secondary whitespace-pre-line">{item.info}</p>
            )}
          </motion.div>
        ))}
      </div>

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.6, delay: 0.4 }}
        className="mt-16 text-center"
      >
        <p className="text-lg text-text-secondary mb-4">
          Pour les réservations, veuillez utiliser notre système en ligne ou nous appeler directement.
        </p>
        <a
          href="/reservations"
          className="inline-block bg-secondary text-primary px-8 py-3 rounded-lg font-semibold hover:bg-secondary/90 transition-colors duration-300"
        >
          Réserver une table
        </a>
      </motion.div>
    </div>
  );
};

export default ContactInfo;