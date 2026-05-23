import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { useMenu } from '../hooks/useMenu';

const Menu = () => {
  const { menuItems, loading, error } = useMenu();
  const [activeCategory, setActiveCategory] = useState('entrées');
  const [hoveredItem, setHoveredItem] = useState(null);

  const categories = ['entrées', 'plats', 'desserts', 'vins'];

  const filteredItems = menuItems.filter(item => item.category === activeCategory);

  const categoryVariants = {
    active: {
      color: '#d4af37',
      borderBottom: '2px solid #d4af37',
      scale: 1.05
    },
    inactive: {
      color: '#ffffff',
      borderBottom: 'none',
      scale: 1
    }
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 }
  };

  const imageVariants = {
    initial: { scale: 1, rotate: 0 },
    hover: { scale: 1.05, rotate: 2 }
  };

  if (loading) return <div className="text-center py-20">Chargement du menu...</div>;
  if (error) return <div className="text-center py-20 text-red-500">Erreur: {error.message}</div>;

  return (
    <section className="py-20 bg-black text-white">
      <div className="container mx-auto px-4">
        <motion.h1
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          className="text-5xl font-bold text-center mb-16 text-gold-500"
        >
          Notre Carte
        </motion.h1>

        <div className="flex justify-center mb-12">
          <div className="flex space-x-8 bg-gray-900 rounded-full p-2">
            {categories.map((category) => (
              <motion.button
                key={category}
                variants={categoryVariants}
                initial="inactive"
                animate={activeCategory === category ? 'active' : 'inactive'}
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={() => setActiveCategory(category)}
                className="px-6 py-3 rounded-full text-lg font-medium transition-all duration-300"
              >
                {category.charAt(0).toUpperCase() + category.slice(1)}
              </motion.button>
            ))}
          </div>
        </div>

        <AnimatePresence mode="wait">
          <motion.div
            key={activeCategory}
            initial="hidden"
            animate="visible"
            exit="hidden"
            variants={itemVariants}
            transition={{ staggerChildren: 0.1 }}
            className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8"
          >
            {filteredItems.map((item) => (
              <motion.div
                key={item.id}
                variants={itemVariants}
                whileHover={{ y: -5 }}
                className="bg-gray-900 rounded-xl overflow-hidden shadow-lg group"
                onMouseEnter={() => setHoveredItem(item.id)}
                onMouseLeave={() => setHoveredItem(null)}
              >
                <div className="relative h-64 overflow-hidden">
                  <motion.img
                    src={item.image_url}
                    alt={item.name}
                    variants={imageVariants}
                    initial="initial"
                    animate={hoveredItem === item.id ? 'hover' : 'initial'}
                    transition={{ type: 'spring', damping: 10 }}
                    className="w-full h-full object-cover"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent"></div>
                </div>

                <div className="p-6 relative z-10">
                  <div className="flex justify-between items-start mb-2">
                    <h3 className="text-xl font-bold text-gold-400">{item.name}</h3>
                    <span className="text-lg font-medium text-gold-300">{item.price} €</span>
                  </div>

                  <motion.p
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.2 }}
                    className="text-gray-300 mb-4"
                  >
                    {item.description}
                  </motion.p>

                  <motion.button
                    whileHover={{ scale: 1.05, backgroundColor: '#d4af37', color: '#000000' }}
                    whileTap={{ scale: 0.95 }}
                    className="w-full py-2 px-4 border border-gold-400 text-gold-400 rounded-md transition-colors duration-300"
                  >
                    Voir les détails
                  </motion.button>
                </div>
              </motion.div>
            ))}
          </motion.div>
        </AnimatePresence>
      </div>
    </section>
  );
};

export default Menu;