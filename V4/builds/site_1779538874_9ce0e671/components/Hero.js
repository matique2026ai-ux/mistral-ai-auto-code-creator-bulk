import React from 'react';
import { motion } from 'framer-motion';
import styles from '../styles/Hero.module.css';

const Hero = () => {
  return (
    <section className={styles.hero}>
      <div className={styles.videoContainer}>
        <video autoPlay loop muted playsInline className={styles.video}>
          <source src="/videos/hero-bg.mp4" type="video/mp4" />
        </video>
        <div className={styles.overlay}></div>
      </div>
      <div className={styles.content}>
        <motion.h1
          className={styles.title}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8, ease: "easeOut" }}
        >
          Le Céleste
        </motion.h1>
        <motion.p
          className={styles.subtitle}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8, ease: "easeOut", delay: 0.2 }}
        >
          Une expérience gastronomique céleste
        </motion.p>
        <motion.div
          className={styles.ctaContainer}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8, ease: "easeOut", delay: 0.4 }}
        >
          <a href="/reservations" className={styles.ctaButton}>
            Réserver une table
          </a>
        </motion.div>
      </div>
    </section>
  );
};

export default Hero;