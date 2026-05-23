import React from 'react';
import { motion } from 'framer-motion';
import Link from 'next/link';

const MenuPreview = () => {
  const menuCategories = [
    { id: 1, name: 'Entrées', image: '/images/menu/entree.jpg' },
    { id: 2, name: 'Plats', image: '/images/menu/plat.jpg' },
    { id: 3, name: 'Desserts', image: '/images/menu/dessert.jpg' },
    { id: 4, name: 'Vins', image: '/images/menu/vin.jpg' }
  ];

  return (
    <section className="py-20 bg-bg-dark">
      <div className="container mx-auto px-4">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          viewport={{ once: true }}
          className="text-center mb-16"
        >
          <h2 className="text-4xl md:text-5xl font-bold mb-4">Découvrez Notre Menu</h2>
          <p className="text-text-secondary max-w-2xl mx-auto">
            Une expérience culinaire unique où chaque plat est une œuvre d'art
          </p>
        </motion.div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
          {menuCategories.map((category, index) => (
            <motion.div
              key={category.id}
              initial={{ opacity: 0, scale: 0.9 }}
              whileInView={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.5, delay: index * 0.1 }}
              viewport={{ once: true }}
              whileHover={{ y: -10, transition: { duration: 0.3 } }}
              className="group"
            >
              <Link href="/menu" className="block relative h-80 rounded-xl overflow-hidden shadow-lg group-hover:shadow-xl transition-shadow duration-300">
                <div className="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-20 transition-all duration-300 z-10"></div>
                <img
                  src={category.image}
                  alt={category.name}
                  className="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500"
                />
                <div className="absolute inset-0 flex items-center justify-center z-20">
                  <h3 className="text-2xl font-bold text-white tracking-wider">
                    {category.name}
                  </h3>
                </div>
              </Link>
            </motion.div>
          ))}
        </div>

        <div className="text-center mt-16">
          <Link
            href="/menu"
            className="inline-block bg-secondary text-primary px-8 py-3 rounded-md font-semibold text-lg hover:bg-secondary/90 transition-colors duration-300 transform hover:scale-105"
          >
            Voir le Menu Complet
          </Link>
        </div>
      </div>
    </section>
  );
};

export default MenuPreview;