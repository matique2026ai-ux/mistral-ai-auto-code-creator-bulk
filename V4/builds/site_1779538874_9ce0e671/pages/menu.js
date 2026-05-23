import { useState, useEffect } from 'react';
import Head from 'next/head';
import Navbar from '../components/Navbar';
import Footer from '../components/Footer';
import { motion, AnimatePresence } from 'framer-motion';
import { fetchMenuItems } from '../lib/api';

export default function MenuPage() {
  const [menuItems, setMenuItems] = useState([]);
  const [activeCategory, setActiveCategory] = useState('entrées');
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const loadMenu = async () => {
      try {
        const data = await fetchMenuItems();
        setMenuItems(data);
        setIsLoading(false);
      } catch (error) {
        console.error('Failed to fetch menu:', error);
        setIsLoading(false);
      }
    };
    
    loadMenu();
  }, []);

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
      borderBottom: 'none'
    }
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 }
  };

  return (
    <div className="min-h-screen bg-black text-white">
      <Head>
        <title>Menu | Le Céleste - Restaurant Gastronomique</title>
        <meta name="description" content="Découvrez notre carte gastronomique avec des plats raffinés et des vins d'exception" />
      </Head>

      <Navbar />

      <main className="container mx-auto px-4 py-16">
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: 0.6 }}
          className="text-center mb-12"
        >
          <h1 className="text-5xl md:text-6xl font-bold mb-4" style={{ fontFamily: 'Playfair Display, serif' }}>
            Notre Carte
          </h1>
          <p className="text-xl text-gray-300 max-w-2xl mx-auto">
            Découvrez nos créations culinaires uniques, élaborées avec des ingrédients d'exception
          </p>
        </motion.div>

        <div className="flex justify-center mb-12">
          <div className="flex space-x-8 bg-gray-900 rounded-full p-2">
            {categories.map((category) => (
              <motion.button
                key={category}
                onClick={() => setActiveCategory(category)}
                variants={categoryVariants}
                initial="inactive"
                animate={activeCategory === category ? 'active' : 'inactive'}
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                transition={{ type: 'spring', stiffness: 300 }}
                className="px-6 py-3 rounded-full text-sm font-medium uppercase tracking-wider transition-all duration-300"
              >
                {category}
              </motion.button>
            ))}
          </div>
        </div>

        {isLoading ? (
          <div className="flex justify-center items-center h-64">
            <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-secondary"></div>
          </div>
        ) : (
          <AnimatePresence mode="wait">
            <motion.div
              key={activeCategory}
              initial="hidden"
              animate="visible"
              exit="hidden"
              variants={{ visible: { transition: { staggerChildren: 0.1 } } }}
              className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8"
            >
              {filteredItems.map((item) => (
                <motion.div
                  key={item.id}
                  variants={itemVariants}
                  whileHover={{ scale: 1.03, rotate: 1 }}
                  transition={{ duration: 0.3 }}
                  className="bg-gray-900 rounded-xl overflow-hidden shadow-lg"
                >
                  <div className="relative h-64">
                    <img
                      src={item.image_url}
                      alt={item.name}
                      className="w-full h-full object-cover"
                      loading="lazy"
                    />
                    <div className="absolute top-4 right-4 bg-accent text-white px-3 py-1 rounded-full text-sm font-bold">
                      {item.price} €
                    </div>
                  </div>
                  <div className="p-6">
                    <h3 className="text-xl font-bold mb-2" style={{ fontFamily: 'Playfair Display, serif' }}>
                      {item.name}
                    </h3>
                    <p className="text-gray-300 text-sm leading-relaxed">
                      {item.description}
                    </p>
                  </div>
                </motion.div>
              ))}
            </motion.div>
          </AnimatePresence>
        )}
      </main>

      <Footer />
    </div>
  );
}

// Static props for SSR
export async function getStaticProps() {
  try {
    const menuItems = await fetchMenuItems();
    return {
      props: {
        initialMenuItems: menuItems
      },
      revalidate: 3600 // Revalidate every hour
    };
  } catch (error) {
    console.error('Error fetching menu items:', error);
    return {
      props: {
        initialMenuItems: []
      },
      revalidate: 300 // Try again in 5 minutes if error
    };
  }
}