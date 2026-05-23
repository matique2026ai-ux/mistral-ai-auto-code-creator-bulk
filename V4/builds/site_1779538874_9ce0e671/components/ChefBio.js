import React from 'react';
import Image from 'next/image';
import { motion } from 'framer-motion';

const ChefBio = () => {
  return (
    <section className="py-20 bg-bg-dark text-text-primary">
      <div className="container mx-auto px-4">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          <motion.div
            className="relative aspect-square rounded-xl overflow-hidden"
            initial={{ opacity: 0, x: -50 }}
            whileInView={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
          >
            <Image
              src="/images/chef.jpg"
              alt="Chef du restaurant Le Céleste"
              fill
              className="object-cover"
              priority
            />
            <div className="absolute inset-0 bg-gradient-to-t from-bg-dark/70 to-transparent"></div>
          </motion.div>

          <div className="space-y-6">
            <motion.h2
              className="text-4xl lg:text-5xl font-bold font-headings"
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.1 }}
              viewport={{ once: true }}
            >
              Notre Chef Étoilé
            </motion.h2>

            <motion.p
              className="text-lg text-text-secondary leading-relaxed"
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.2 }}
              viewport={{ once: true }}
            >
              Avec plus de 20 ans d'expérience dans les cuisines des plus grands restaurants parisiens,
              notre chef <span className="text-secondary font-semibold">Thierry Laurent</span> a rejoint
              Le Céleste en 2018 pour créer une expérience culinaire unique.
            </motion.p>

            <motion.div
              className="space-y-4"
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.3 }}
              viewport={{ once: true }}
            >
              <p className="text-text-secondary">
                Formé auprès de légendes comme Alain Ducasse et Gordon Ramsay,
                il a développé une philosophie culinaire qui marie tradition française
                et innovations audacieuses.
              </p>

              <p className="text-text-secondary">
                Sa signature ? Des plats où chaque ingrédient raconte une histoire,
                avec un respect absolu des produits de saison.
              </p>
            </motion.div>

            <motion.blockquote
              className="border-l-4 border-secondary pl-6 italic text-xl text-secondary"
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.4 }}
              viewport={{ once: true }}
            >
              "La cuisine est un art éphémère qui doit émouvoir tous les sens."
            </motion.blockquote>

            <motion.div
              className="flex flex-wrap gap-4 pt-4"
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.5 }}
              viewport={{ once: true }}
            >
              <div className="bg-primary/50 px-4 py-2 rounded-lg">
                <span className="text-secondary font-semibold">2 Étoiles Michelin</span>
              </div>
              <div className="bg-primary/50 px-4 py-2 rounded-lg">
                <span className="text-secondary font-semibold">Meilleur Chef 2022</span>
              </div>
            </motion.div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default ChefBio;