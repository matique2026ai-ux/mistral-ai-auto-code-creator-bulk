import Head from 'next/head';
import Navbar from '../components/Navbar';
import Hero from '../components/Hero';
import MenuPreview from '../components/MenuPreview';
import ChefSection from '../components/ChefSection';
import Testimonials from '../components/Testimonials';
import GalleryPreview from '../components/GalleryPreview';
import Footer from '../components/Footer';

export default function Home() {
  return (
    <div className="min-h-screen bg-bg-dark text-text-primary">
      <Head>
        <title>Le Céleste - Restaurant Gastronomique</title>
        <meta name="description" content="Découvrez une expérience gastronomique céleste au restaurant Le Céleste. Réservez votre table dès maintenant." />
        <meta name="keywords" content="restaurant gastronomique, cuisine fine, réservation en ligne, expérience culinaire" />
        <meta name="author" content="Le Céleste" />
        <meta property="og:title" content="Le Céleste - Restaurant Gastronomique" />
        <meta property="og:description" content="Découvrez une expérience gastronomique céleste au restaurant Le Céleste." />
        <meta property="og:type" content="website" />
        <meta property="og:url" content="https://www.leceleste.com" />
        <meta property="og:image" content="https://www.leceleste.com/images/og-image.jpg" />
        <link rel="icon" href="/favicon.ico" />
      </Head>

      <Navbar />

      <main>
        <Hero />
        <MenuPreview />
        <ChefSection />
        <Testimonials />
        <GalleryPreview />
      </main>

      <Footer />
    </div>
  );
}