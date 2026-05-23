import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { StarIcon } from '@heroicons/react/24/solid';

const testimonials = [
  {
    id: 1,
    name: 'Sophie Martin',
    comment: 'Une expérience culinaire inoubliable. Chaque plat était une explosion de saveurs parfaitement équilibrées.',
    rating: 5,
    date: '15 mai 2023'
  },
  {
    id: 2,
    name: 'Jean-Luc Bernard',
    comment: 'Le service était impeccable et l\'ambiance envoûtante. Le chef a su nous surprendre avec des associations audacieuses.',
    rating: 5,
    date: '3 juin 2023'
  },
  {
    id: 3,
    name: 'Claire Dubois',
    comment: 'Un moment magique. La présentation des plats est une véritable œuvre d\'art. Nous reviendrons sans hésiter.',
    rating: 5,
    date: '18 juillet 2023'
  },
  {
    id: 4,
    name: 'Pierre Moreau',
    comment: 'Le menu dégustation était une symphonie de textures et de goûts. Une adresse à recommander absolument.',
    rating: 5,
    date: '22 août 2023'
  }
];

export default function Testimonials() {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [direction, setDirection] = useState(1);

  useEffect(() => {
    const interval = setInterval(() => {
      setDirection(1);
      setCurrentIndex(prev => (prev + 1) % testimonials.length);
    }, 8000);

    return () => clearInterval(interval);
  }, []);

  const goToPrevious = () => {
    setDirection(-1);
    setCurrentIndex(prev => (prev - 1 + testimonials.length) % testimonials.length);
  };

  const goToNext = () => {
    setDirection(1);
    setCurrentIndex(prev => (prev + 1) % testimonials.length);
  };

  const variants = {
    enter: (direction) => ({
      x: direction > 0 ? 1000 : -1000,
      opacity: 0
    }),
    center: {
      x: 0,
      opacity: 1
    },
    exit: (direction) => ({
      x: direction < 0 ? 1000 : -1000,
      opacity: 0
    })
  };

  return (
    <section className="py-20 bg-black relative overflow-hidden">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <h2 className="text-4xl md:text-5xl font-bold text-white mb-4">
            Témoignages de nos clients
          </h2>
          <p className="text-xl text-gray-300 max-w-3xl mx-auto">
            Découvrez ce que nos clients disent de leur expérience au Céleste
          </p>
        </div>

        <div className="relative">
          <AnimatePresence initial={false} custom={direction} mode='wait'>
            <motion.div
              key={currentIndex}
              custom={direction}
              variants={variants}
              initial="enter"
              animate="center"
              exit="exit"
              transition={{ duration: 0.8, ease: [0.4, 0, 0.2, 1] }}
              className="bg-black bg-opacity-50 backdrop-blur-md rounded-2xl p-8 md:p-12 border border-gray-800"
            >
              <div className="flex flex-col md:flex-row items-center gap-8">
                <div className="md:w-1/3 flex justify-center">
                  <div className="w-24 h-24 rounded-full bg-gradient-to-br from-yellow-600 to-yellow-800 flex items-center justify-center">
                    <span className="text-4xl font-bold text-white">"</span>
                  </div>
                </div>

                <div className="md:w-2/3 text-center md:text-left">
                  <div className="flex justify-center md:justify-start mb-4">
                    {[...Array(testimonials[currentIndex].rating)].map((_, i) => (
                      <StarIcon key={i} className="h-6 w-6 text-yellow-500 fill-current" />
                    ))}
                  </div>

                  <blockquote className="text-2xl md:text-3xl font-medium text-white mb-6 leading-relaxed">
                    {testimonials[currentIndex].comment}
                  </blockquote>

                  <div className="border-t border-gray-700 pt-6">
                    <p className="text-lg font-semibold text-white">
                      {testimonials[currentIndex].name}
                    </p>
                    <p className="text-gray-400">
                      {testimonials[currentIndex].date}
                    </p>
                  </div>
                </div>
              </div>
            </motion.div>
          </AnimatePresence>

          <button
            onClick={goToPrevious}
            className="absolute left-4 top-1/2 -translate-y-1/2 z-10 p-3 rounded-full bg-black bg-opacity-50 hover:bg-opacity-75 transition-all duration-300"
            aria-label="Témoignage précédent"
          >
            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
          </button>

          <button
            onClick={goToNext}
            className="absolute right-4 top-1/2 -translate-y-1/2 z-10 p-3 rounded-full bg-black bg-opacity-50 hover:bg-opacity-75 transition-all duration-300"
            aria-label="Témoignage suivant"
          >
            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        <div className="flex justify-center mt-8 gap-2">
          {testimonials.map((_, index) => (
            <button
              key={index}
              onClick={() => {
                setDirection(index > currentIndex ? 1 : -1);
                setCurrentIndex(index);
              }}
              className={`w-3 h-3 rounded-full transition-all duration-300 ${
                index === currentIndex ? 'bg-yellow-500 w-6' : 'bg-gray-600 hover:bg-gray-400'
              }`}
              aria-label={`Aller au témoignage ${index + 1}`}
            />
          ))}
        </div>
      </div>

      <div className="absolute inset-0 pointer-events-none">
        <div className="absolute top-0 left-0 w-64 h-64 bg-gradient-to-br from-yellow-500 to-transparent opacity-10 rounded-full blur-3xl" />
        <div className="absolute bottom-0 right-0 w-96 h-96 bg-gradient-to-tl from-yellow-500 to-transparent opacity-10 rounded-full blur-3xl" />
      </div>
    </section>
  );
}