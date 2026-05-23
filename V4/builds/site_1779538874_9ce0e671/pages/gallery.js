import { useState, useEffect } from 'react';
import Head from 'next/head';
import Navbar from '../components/Navbar';
import Footer from '../components/Footer';
import { motion } from 'framer-motion';
import Lightbox from 'react-image-lightbox';
import 'react-image-lightbox/style.css';

export default function Gallery() {
  const [images, setImages] = useState([]);
  const [isOpen, setIsOpen] = useState(false);
  const [photoIndex, setPhotoIndex] = useState(0);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchGalleryImages = async () => {
      try {
        const response = await fetch('/api/gallery');
        const data = await response.json();
        setImages(data.gallery_images);
        setLoading(false);
      } catch (error) {
        console.error('Error fetching gallery images:', error);
        setLoading(false);
      }
    };

    fetchGalleryImages();
  }, []);

  const openLightbox = (index) => {
    setPhotoIndex(index);
    setIsOpen(true);
  };

  const closeLightbox = () => {
    setIsOpen(false);
  };

  const movePrev = () => {
    setPhotoIndex((photoIndex + images.length - 1) % images.length);
  };

  const moveNext = () => {
    setPhotoIndex((photoIndex + 1) % images.length);
  };

  return (
    <div className="min-h-screen bg-bg-dark text-text-primary">
      <Head>
        <title>Galerie | Le Céleste</title>
        <meta name="description" content="Découvrez notre galerie photo - Une expérience culinaire visuelle au restaurant Le Céleste" />
      </Head>

      <Navbar />

      <main className="container mx-auto px-4 py-16">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          className="text-center mb-12"
        >
          <h1 className="text-4xl md:text-5xl font-headings mb-4">Notre Galerie</h1>
          <p className="text-text-secondary max-w-2xl mx-auto">
            Plongez dans l'atmosphère unique du Céleste à travers nos images
          </p>
        </motion.div>

        {loading ? (
          <div className="flex justify-center items-center h-64">
            <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-secondary"></div>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {images.map((image, index) => (
              <motion.div
                key={image.id}
                whileHover={{ scale: 1.05, rotate: 1 }}
                whileTap={{ scale: 0.95 }}
                transition={{ duration: 0.3 }}
                className="relative overflow-hidden rounded-lg cursor-pointer"
                onClick={() => openLightbox(index)}
              >
                <img
                  src={image.url}
                  alt={image.alt_text}
                  className="w-full h-64 object-cover"
                  loading="lazy"
                />
                <div className="absolute inset-0 bg-black bg-opacity-30 flex items-end p-4">
                  <h3 className="text-white font-medium">{image.alt_text}</h3>
                </div>
              </motion.div>
            ))}
          </div>
        )}

        {isOpen && (
          <Lightbox
            mainSrc={images[photoIndex].url}
            nextSrc={images[(photoIndex + 1) % images.length].url}
            prevSrc={images[(photoIndex + images.length - 1) % images.length].url}
            onCloseRequest={closeLightbox}
            onMovePrevRequest={movePrev}
            onMoveNextRequest={moveNext}
            imageTitle={images[photoIndex].alt_text}
            imageCaption={images[photoIndex].alt_text}
            animationOnKeyInput={true}
            reactModalStyle={{ overlay: { zIndex: 1000 } }}
          />
        )}
      </main>

      <Footer />
    </div>
  );
}