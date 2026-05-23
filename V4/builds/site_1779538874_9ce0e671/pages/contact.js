import Head from 'next/head';
import Navbar from '../components/Navbar';
import ContactInfo from '../components/ContactInfo';
import Map from '../components/Map';
import Footer from '../components/Footer';

export default function ContactPage() {
  return (
    <>
      <Head>
        <title>Contact | Le Céleste - Restaurant Gastronomique</title>
        <meta name="description" content="Contactez Le Céleste pour des réservations ou des informations. Découvrez notre emplacement et nos coordonnées." />
        <meta property="og:title" content="Contact | Le Céleste" />
        <meta property="og:description" content="Contactez Le Céleste pour des réservations ou des informations." />
        <meta property="og:type" content="website" />
        <meta property="og:url" content="https://www.leceleste.com/contact" />
      </Head>
      
      <Navbar />
      
      <main className="min-h-screen bg-bg-dark text-text-primary">
        <div className="container mx-auto px-4 py-16">
          <h1 className="text-5xl font-bold text-center mb-12 text-secondary">Nous Contacter</h1>
          
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <ContactInfo />
            <Map />
          </div>
        </div>
      </main>
      
      <Footer />
    </>
  );
}