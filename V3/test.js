
const TRANSLATIONS = {
  en: {
    config: "Project Setup",
    site_type: "Site Type",
    output_lang: "Generated Site Language",
    css_framework: "CSS Style Framework",
    brief: "Mission Brief",
    project_title: "Project Name",
    brief_who: "Who you are / Brand identity",
    brief_target: "Target Audience",
    brief_monetize: "Monetization / Goal",
    launch: "🚀 Launch Autonomous Build",
    add_key: "Add API Key",
    save: "Save Key",
    saved_keys: "Saved API Keys",
    system_stats: "System Performance",
    lbl_keys: "Keys",
    lbl_tokens: "Tokens",
    lbl_projs: "Projects",
    recent_projects: "Generated Projects",
    nav_title: "Autonomous Agent Pipeline",
    standing_by: "Ready to launch agent loop...",
    healing_active: "Autonomous Self-Healing Loop Active",
    clear: "Clear",
    preview_title: "Live Sandbox Interactive Preview",
    
    // New tab translations
    tab_builder: "⚡ Builder",
    tab_keys: "🔑 Keys",
    tab_projects: "📁 Projects",

    // New label & placeholder translations
    label_alias: "Alias / Label",
    label_mistral: "Mistral API Key",
    placeholder_alias: "Pro Account, Backup Key...",
    placeholder_mistral: "Paste your API key here",
    placeholder_title: "Ex: SwiftSaas, DevBlog...",
    placeholder_who: "Ex: AI freelance developer offering custom automation solutions.",
    placeholder_target: "Ex: Small business owners, entrepreneurs who need to save time.",
    placeholder_monetize: "Ex: Premium subscription plans, custom project requests.",

    // Alerts
    alert_enter_key: "Please enter label and API key values!",
    alert_delete_key: "Are you sure you want to delete this API Key?",
    alert_delete_project: "Are you sure you want to delete this project? This will completely clear all files on disk!",
    alert_fill_brief: "Please fill out all mission brief fields before launching the agent loop!",

    // Dynamic Lists translations
    empty_keys: "No keys saved. Add one above!",
    key_active: "Active",
    key_inactive: "Inactive / Suspended",
    errors: "Errors",
    btn_reset: "Reset",
    empty_projects: "No projects found. Launch a build!",
    proj_ready: "Ready",
    proj_building: "Building",
    qa_rating: "QA Rating",
    files: "Files",
    btn_open_sandbox: "Open Sandbox",
    btn_delete: "Delete",

    // Dynamic Badges & Stats
    active_keys: "Active Keys",
    tokens_used: "Tokens Used",
    active_model_desc: "Rotating API Key pool • Active Model: devstral-2512",

    // Dynamic Progress / Self-healing
    progress_key_check: "Checking API Key Pool readiness...",
    progress_digest: "Digesting mission parameters & loading AI engine...",
    progress_research: "Performing autonomous competitor & niche research...",
    progress_architecture: "Structuring application directory & pages via Mistral...",
    progress_css: "Styling aesthetic UI components...",
    progress_config: "Writing global PHP platform context config...",
    progress_generation: "Generating application pages source code...",
    progress_page: "Generating dynamic code for page: ",
    progress_persistence: "Saving compiled files to sandbox folder...",
    progress_qa: "Performing autonomous code quality checks...",
    progress_healing: "Healing issue in ",
    progress_seo: "Structuring index crawlers SEO parameters...",
    progress_sandbox: "Deploying sandbox parameters live...",
    healing_banner_status: "Debugging generated PHP syntax & layout flow issues...",
    correcting: "Correcting",
    in_file: "in",
    pass: "Pass",
    iteration: "Iter",

    // Select option translations
    opt_saas: "⚡ SaaS / Web App",
    opt_blog: "📝 Blog / Magazine",
    opt_store: "🛒 E-Commerce Store",
    opt_portfolio: "🎨 Portfolio / Agency",
    opt_landing: "🚀 Landing Page",
    opt_corporate: "🏢 Corporate / Business",
    opt_en: "English",
    opt_ar: "العربية (RTL)",
    opt_fr: "Français",
    opt_es: "Español",
    opt_de: "Deutsch",
    opt_vanilla: "Vanilla CSS (Modern, premium)",
    opt_tailwind: "Tailwind CSS (Utility-first CDN)",
    opt_bootstrap: "Bootstrap 5 (Clean, structured)",

    tech_stack: "Technology Stack & Platform",
    opt_auto: "🤖 Pure AI Autonomy (Best for the mission)",
    opt_html_css_js: "🌐 Pure Frontend (HTML5 / Vanilla CSS / ES6 JS)",
    opt_tailwind_html: "🎨 Tailwind CSS + HTML5 + Modern JS",
    opt_nodejs: "⚡ Node.js (Express backend + HTML)",
    opt_python: "🐍 Python (Flask / FastAPI web app)",
    opt_php_stack: "🐘 PHP Full Stack",
    opt_mobile: "📱 Mobile Targets (Android Kotlin / iOS Swift UI)"
  },
  ar: {
    config: "إعدادات المشروع",
    site_type: "نوع الموقع",
    output_lang: "لغة الموقع المنشأ",
    css_framework: "إطار عمل التنسيق (CSS)",
    brief: "موجز المشروع (Brief)",
    project_title: "اسم المشروع",
    brief_who: "هويتك / طبيعة عملك",
    brief_target: "الجمهور المستهدف",
    brief_monetize: "طريقة الاستثمار / الهدف",
    launch: "🚀 إطلاق البناء الذاتي المستقل",
    add_key: "إضافة مفتاح API",
    save: "حفظ المفتاح",
    saved_keys: "مفاتيح API المحفوظة",
    system_stats: "أداء النظام",
    lbl_keys: "مفاتيح",
    lbl_tokens: "توكنز",
    lbl_projs: "مشاريع",
    recent_projects: "المشاريع المنشأة",
    nav_title: "خطوات المعالجة الذاتية المستقلة",
    standing_by: "جاهز لإطلاق المعالجة المستقلة...",
    healing_active: "دورة التصحيح والمعالجة الذاتية نشطة",
    clear: "مسح",
    preview_title: "معاينة مباشرة تفاعلية للموقع",

    // New tab translations
    tab_builder: "⚡ الباني",
    tab_keys: "🔑 المفاتيح",
    tab_projects: "📁 المشاريع",

    // New label & placeholder translations
    label_alias: "الاسم المستعار / التسمية",
    label_mistral: "مفتاح Mistral API Key",
    placeholder_alias: "حساب احترافي، مفتاح احتياطي...",
    placeholder_mistral: "أضف مفتاح API الخاص بك هنا",
    placeholder_title: "مثال: SwiftSaas, DevBlog...",
    placeholder_who: "مثال: مطور مستقل للذكاء الاصطناعي يقدم حلول أتمتة مخصصة.",
    placeholder_target: "مثال: أصحاب المشاريع الصغيرة، رواد الأعمال الذين يريدون توفير الوقت.",
    placeholder_monetize: "مثال: خطط اشتراك مميزة، طلبات مشاريع مخصصة.",

    // Alerts
    alert_enter_key: "يرجى إدخال التسمية وقيمة مفتاح API!",
    alert_delete_key: "هل أنت متأكد من رغبتك في حذف مفتاح API هذا؟",
    alert_delete_project: "هل أنت متأكد من رغبتك في حذف هذا المشروع؟ سيؤدي ذلك إلى حذف جميع الملفات نهائيًا من القرص!",
    alert_fill_brief: "يرجى ملء جميع حقول موجز المشروع قبل إطلاق دورة المعالجة!",

    // Dynamic Lists translations
    empty_keys: "لا توجد مفاتيح محفوظة. أضف مفتاحًا أعلاه!",
    key_active: "نشط",
    key_inactive: "غير نشط / معطل",
    errors: "الأخطاء",
    btn_reset: "إعادة تعيين",
    empty_projects: "لم يتم العثور على مشاريع. ابدأ بناء موقعك الآن!",
    proj_ready: "جاهز",
    proj_building: "جاري البناء",
    qa_rating: "تقييم الجودة",
    files: "الملفات",
    btn_open_sandbox: "افتح بيئة العمل",
    btn_delete: "حذف",

    // Dynamic Badges & Stats
    active_keys: "مفاتيح نشطة",
    tokens_used: "رمز مستخدم (Tokens)",
    active_model_desc: "مجموعة تدوير مفاتيح API • النموذج النشط: devstral-2512",

    // Dynamic Progress / Self-healing
    progress_key_check: "التحقق من جاهزية مجموعة مفاتيح API...",
    progress_digest: "تحليل مدخلات ومعايير المشروع وتحميل محرك الذكاء الاصطناعي...",
    progress_research: "إجراء أبحاث السوق والمنافسين بشكل مستقل...",
    progress_architecture: "تصميم هيكلية الموقع والصفحات عبر Mistral...",
    progress_css: "تصميم التنسيقات الجمالية وعناصر واجهة المستخدم...",
    progress_config: "كتابة ملف إعدادات الخادم المشترك PHP...",
    progress_generation: "توليد الكود المصدري لصفحات الموقع بالكامل...",
    progress_page: "توليد الكود التفاعلي لصفحة: ",
    progress_persistence: "حفظ وتثبيت الملفات البرمجية داخل بيئة العمل...",
    progress_qa: "إجراء فحص الجودة البرمجية واكتشاف الأخطاء تلقائيًا...",
    progress_healing: "تصحيح المشكلة البرمجية في صفحة ",
    progress_seo: "إعداد معايير محركات البحث SEO وملفات Sitemap...",
    progress_sandbox: "نشر وتدشين الموقع في بيئة المعاينة التفاعلية...",
    healing_banner_status: "جاري تصحيح أخطاء بناء PHP وتعديل انسيابية التنسيقات...",
    correcting: "جاري تصحيح",
    in_file: "في",
    pass: "المحاولة",
    iteration: "المحاولة",

    // Select option translations
    opt_saas: "⚡ تطبيق ويب / SaaS",
    opt_blog: "📝 مدونة / مجلة",
    opt_store: "🛒 متجر إلكتروني",
    opt_portfolio: "🎨 معرض أعمال / وكالة",
    opt_landing: "🚀 صفحة هبوط تسويقية",
    opt_corporate: "🏢 موقع شركات / أعمال",
    opt_en: "الإنجليزية",
    opt_ar: "العربية (RTL)",
    opt_fr: "الفرنسية",
    opt_es: "الإسبانية",
    opt_de: "الألمانية",
    opt_vanilla: "Vanilla CSS (عصري، فاخر)",
    opt_tailwind: "Tailwind CSS (إطار عمل مرن)",
    opt_bootstrap: "Bootstrap 5 (منظم ونظيف)",

    tech_stack: "بنية التقنيات والمنصة (Tech Stack)",
    opt_auto: "🤖 ذكاء اصطناعي مستقل (تحديد تلقائي للأنسب)",
    opt_html_css_js: "🌐 فرونت إند فقط (HTML5 / Vanilla CSS / ES6 JS)",
    opt_tailwind_html: "🎨 إطار Tailwind CSS + HTML5 + واجهات حديثة",
    opt_nodejs: "⚡ بيئة Node.js (خادم Express + واجهات تفاعلية)",
    opt_python: "🐍 لغة Python (إطار Flask / FastAPI)",
    opt_php_stack: "🐘 بيئة PHP متكاملة",
    opt_mobile: "📱 واجهات هواتف ذكية (Android Kotlin / iOS Swift UI)"
  }
};

let currentLang = 'en';

function t(key) {
  if (TRANSLATIONS[currentLang] && TRANSLATIONS[currentLang][key]) {
    return TRANSLATIONS[currentLang][key];
  }
  return TRANSLATIONS['en'][key] || key;
}

function setUILang(lang) {
  currentLang = lang;
  document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.classList.toggle('active', btn.textContent.toLowerCase() === lang.toLowerCase() || (lang === 'ar' && btn.textContent === 'العربية'));
  });
  
  const root = document.getElementById('appRoot');
  if (lang === 'ar') {
    root.classList.add('rtl');
  } else {
    root.classList.remove('rtl');
  }

  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    if (TRANSLATIONS[lang] && TRANSLATIONS[lang][key]) {
      el.textContent = TRANSLATIONS[lang][key];
    }
  });

  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    const key = el.getAttribute('data-i18n-placeholder');
    if (TRANSLATIONS[lang] && TRANSLATIONS[lang][key]) {
      el.setAttribute('placeholder', TRANSLATIONS[lang][key]);
    }
  });

  // Re-load lists to update texts
  if (typeof loadKeys === 'function') loadKeys().catch(() => {});
  if (typeof loadProjects === 'function') loadProjects().catch(() => {});
}

// Default page names mapping
const DEFAULT_PAGES_MAP = {
  saas: ['index.php', 'features.php', 'pricing.php', 'login.php', 'contact.php'],
  blog: ['index.php', 'blog.php', 'article.php', 'about.php', 'contact.php'],
  store: ['index.php', 'shop.php', 'product.php', 'cart.php', 'contact.php'],
  portfolio: ['index.php', 'work.php', 'about.php', 'services.php', 'contact.php'],
  landing: ['index.php', 'features.php', 'testimonials.php', 'pricing.php', 'faq.php'],
  corporate: ['index.php', 'about.php', 'services.php', 'team.php', 'contact.php']
};

function updateDefaultPages() {
  const type = document.getElementById('siteType').value;
  log('sys', `Selected Site Type changed to: ${type.toUpperCase()}`);
}
