import React from 'react';
import { motion } from 'framer-motion';
import Image from 'next/image';

const ChefSection = () => {
  return (
    <section className="py-20 bg-bg-dark text-text-primary">
      <div className="container mx-auto px-4">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          <motion.div
            className="relative"
            initial={{ opacity: 0, x: -50 }}
            whileInView={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
          >
            <div className="relative w-full h-96 lg:h-[500px] rounded-xl overflow-hidden">
              <Image
                src="/images/chef.jpg"
                alt="Chef du restaurant Le Céleste"
                fill
                className="object-cover grayscale hover:grayscale-0 transition-all duration-500"
                priority
              />
              <div className="absolute inset-0 bg-gradient-to-t from-bg-dark/70 to-transparent"></div>
            </div>
          </motion.div>

          <motion.div
            className="space-y-6"
            initial={{ opacity: 0, x: 50 }}
            whileInView={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.6, delay: 0.2 }}
            viewport={{ once: true }}
          >
            <h2 className="text-4xl lg:text-5xl font-headings font-bold">
              Notre Chef Étoilé
            </h2>

            <div className="space-y-4 text-text-secondary">
              <p className="text-lg leading-relaxed">
                Chef <span className="text-secondary font-semibold">Thierry Laurent</span> incarne l'excellence culinaire avec plus de 25 ans d'expérience dans les plus grands restaurants étoilés de France.
              </p>

              <p className="leading-relaxed">
                Formé auprès des maîtres de la gastronomie française, il a développé une philosophie unique alliant tradition et innovation. Son approche respectueuse des produits de saison et son talent pour sublimer les saveurs ont valu à Le Céleste deux étoiles au Guide Michelin.
              </p>

              <p className="leading-relaxed">
                "La cuisine doit raconter une histoire et émouvoir. Chaque plat est une œuvre d'art éphémère qui doit laisser une impression durable."
              </p>
            </div>

            <div className="flex flex-wrap gap-4 pt-4">
              <div className="flex items-center gap-2">
                <span className="text-secondary">★★</span>
                <span>Guide Michelin</span>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-secondary">⭐⭐⭐⭐⭐</span>
                <span>Gault & Millau</span>
              </div>
            </div>

            <motion.button
              whileHover={{ scale: 1.05 }}
              whileTap={{ scale: 0.95 }}
              className="mt-8 px-8 py-3 bg-secondary text-primary font-semibold rounded-md hover:bg-secondary/90 transition-colors"
            >
              Découvrir sa philosophie
            </motion.button>
          </motion.div>
        </div>
      </div>
    </section>
  );
};

export default ChefSection;