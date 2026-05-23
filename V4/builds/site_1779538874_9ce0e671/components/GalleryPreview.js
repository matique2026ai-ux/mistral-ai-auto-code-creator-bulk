import React, { useState } from 'react';
import Image from 'next/image';
import { motion } from 'framer-motion';

const GalleryPreview = ({ images = [] }) => {
  const [selectedImage, setSelectedImage] = useState(null);
  const [isLightboxOpen, setIsLightboxOpen] = useState(false);

  const openLightbox = (image) => {
    setSelectedImage(image);
    setIsLightboxOpen(true);
  };

  const closeLightbox = () => {
    setIsLightboxOpen(false);
    setSelectedImage(null);
  };

  const navigateImage = (direction) => {
    if (!selectedImage) return;
    const currentIndex = images.findIndex(img => img.id === selectedImage.id);
    let newIndex;
    if (direction === 'next') {
      newIndex = (currentIndex + 1) % images.length;
    } else {
      newIndex = (currentIndex - 1 + images.length) % images.length;
    }
    setSelectedImage(images[newIndex]);
  };

  return (
    <section className="py-20 bg-bg-dark">
      <div className="container mx-auto px-4">
        <motion.h2
          className="text-4xl font-bold text-center mb-12 text-text-primary"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
        >
          Galerie
        </motion.h2>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {images.slice(0, 6).map((image, index) => (
            <motion.div
              key={image.id}
              className="relative overflow-hidden rounded-lg cursor-pointer group"
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.5, delay: index * 0.1 }}
              whileHover={{ scale: 1.05 }}
              onClick={() => openLightbox(image)}
            >
              <Image
                src={image.url}
                alt={image.alt_text}
                width={500}
                height={400}
                className="w-full h-64 object-cover transition-transform duration-300 group-hover:scale-110"
              />
              <div className="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                <span className="text-white text-lg font-medium">Voir en grand</span>
              </div>
            </motion.div>
          ))}
        </div>

        <div className="text-center mt-12">
          <motion.button
            className="px-8 py-3 bg-secondary text-primary rounded-md font-medium hover:bg-secondary/90 transition-colors"
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
          >
            Voir toute la galerie
          </motion.button>
        </div>
      </div>

      {/* Lightbox Modal */}
      {isLightboxOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-90 backdrop-blur-sm" onClick={closeLightbox}>
          <motion.div
            className="relative max-w-4xl mx-4"
            initial={{ scale: 0.9, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            transition={{ duration: 0.3 }}
            onClick={(e) => e.stopPropagation()}
          >
            <button
              className="absolute top-4 right-4 text-white text-3xl z-10 hover:text-secondary transition-colors"
              onClick={closeLightbox}
            >
              ×
            </button>

            <div className="relative">
              <Image
                src={selectedImage.url}
                alt={selectedImage.alt_text}
                width={1200}
                height={800}
                className="w-full h-auto max-h-[80vh] object-contain"
              />

              <button
                className="absolute left-4 top-1/2 transform -translate-y-1/2 text-white text-4xl hover:text-secondary transition-colors"
                onClick={(e) => {
                  e.stopPropagation();
                  navigateImage('prev');
                }}
              >
                ‹
              </button>

              <button
                className="absolute right-4 top-1/2 transform -translate-y-1/2 text-white text-4xl hover:text-secondary transition-colors"
                onClick={(e) => {
                  e.stopPropagation();
                  navigateImage('next');
                }}
              >
                ›
              </button>
            </div>

            <div className="text-center mt-4 text-white">
              <p className="text-lg">{selectedImage.alt_text}</p>
              <p className="text-sm text-text-secondary mt-1">
                {images.findIndex(img => img.id === selectedImage.id) + 1} / {images.length}
              </p>
            </div>
          </motion.div>
        </div>
      )}
    </section>
  );
};

export default GalleryPreview;