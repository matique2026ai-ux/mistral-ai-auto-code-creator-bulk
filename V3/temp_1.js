
// Pipeline definition: 12 stages with auto-healing, self-reflection, and error recovery features.
const PIPELINE_STAGES = [
  { id: 'key_check', label: 'API Key Rotator & Readiness' },
  { id: 'brief_digest', label: 'Brief Digestion & AI Modeling' },
  { id: 'niche_research', label: 'Autonomous Niche & Competitor Research' },
  { id: 'arch_design', label: 'AI Structural & UI Architecture' },
  { id: 'css_shared', label: 'CSS Stylesheet & Layout Framework' },
  { id: 'config_shared', label: 'PHP Server Configuration Setup' },
  { id: 'page_generation', label: 'PHP Page Source Generation' },
  { id: 'file_persistance', label: 'Hard-Disk Persistent Writing' },
  { id: 'ai_qa_reflection', label: 'Autonomous AI QA Code Reflection' },
  { id: 'self_healing', label: 'Self-Correction & Healing Cycle' },
  { id: 'seo_engine', label: 'SEO robots.txt + Sitemap Generation' },
  { id: 'sandbox_publish', label: 'Live Sandbox Environment Deploy' }
];

let isCurrentlyBuilding = false;
let activeProjectId = null;
let activeProjectFolder = '';
let activeProjectSlug = '';
let activeKeyId = null;
let activeApiKeyVal = '';
let activeSiteArchitecture = null;

// Populate steps inside steps-grid
function renderPipelineStages() {
  const grid = document.getElementById('stepsGrid');
  grid.innerHTML = PIPELINE_STAGES.map((s, idx) => `
    <div class="step-pill" id="step-${s.id}">
      <span class="step-num" style="font-family: var(--font-mono); opacity: 0.5;">${String(idx+1).padStart(2, '0')}</span>
      <span>${s.label}</span>
    </div>
  `).join('');
}

function updateStageStatus(stageId, status) {
  const el = document.getElementById(`step-${stageId}`);
  if (!el) return;
  el.className = 'step-pill';
  if (status === 'active') el.classList.add('active');
  if (status === 'completed') el.classList.add('completed');
  if (status === 'failed') el.classList.add('failed');
}

// LOGGING SYSTEM
function log(level, message) {
  const consoleEl = document.getElementById('terminalConsole');
  const now = new Date();
  const timeStr = now.toTimeString().split(' ')[0];
  
  const row = document.createElement('div');
  row.className = 'log-row';
  row.innerHTML = `
    <span class="log-time">${timeStr}</span>
    <span class="log-badge badge-${level}">${level}</span>
    <span class="log-msg">${message}</span>
  `;
  consoleEl.appendChild(row);
  consoleEl.scrollTop = consoleEl.scrollHeight;

  if (typeof activeProjectId !== 'undefined' && activeProjectId) {
    fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'append_log',
        project_id: activeProjectId,
        level: level,
        message: message
      })
    }).catch(() => {});
  }
}

function clearTerminalLogs() {
  document.getElementById('terminalConsole').innerHTML = '';
}

// UI TABS SWITCHER
function switchTab(tabName) {
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.panel-section').forEach(p => p.style.display = 'none');
  
  if (tabName === 'build') {
    document.getElementById('tabBtnBuild').classList.add('active');
    document.getElementById('panelBuild').style.display = 'block';
  } else if (tabName === 'keys') {
    document.getElementById('tabBtnKeys').classList.add('active');
    document.getElementById('panelKeys').style.display = 'block';
    loadKeys();
  } else if (tabName === 'projects') {
    document.getElementById('tabBtnProjects').classList.add('active');
    document.getElementById('panelProjects').style.display = 'block';
    loadProjects();
    loadStats();
  }
}

// -------------------------------------------------------------
// AI JSON ROBUST PARSER & REPAIR ENGINE
// -------------------------------------------------------------
function cleanRawJsonText(text) {
  let str = text.trim();
  str = str.replace(/^```\s*json/i, ''); // strip ```json
  str = str.replace(/^```(?:json)?\s*/i, '');
  str = str.replace(/\s*```$/, '');
  return str.trim();
}

function repairTruncatedJson(str) {
  str = str.trim();
  let inString = false;
  let escapeNext = false;
  let stack = [];
  
  for (let i = 0; i < str.length; i++) {
    let char = str[i];
    if (escapeNext) {
      escapeNext = false;
      continue;
    }
    if (char === '\\') {
      escapeNext = true;
      continue;
    }
    if (inString) {
      if (char === '"') {
        inString = false;
      }
    } else {
      if (char === '"') {
        inString = true;
      } else if (char === '{') {
        stack.push('}');
      } else if (char === '[') {
        stack.push(']');
      } else if (char === '}') {
        if (stack[stack.length - 1] === '}') stack.pop();
      } else if (char === ']') {
        if (stack[stack.length - 1] === ']') stack.pop();
      }
    }
  }
  
  let repaired = str;
  if (inString) {
    repaired += '"';
  }
  while (stack.length > 0) {
    repaired += stack.pop();
  }
  return repaired;
}

function safeJsonParse(text) {
  let cleaned = cleanRawJsonText(text);
  try {
    return JSON.parse(cleaned);
  } catch (e) {
    const repaired = repairTruncatedJson(cleaned);
    try {
      return JSON.parse(repaired);
    } catch (err) {
      throw new Error(`JSON parse and repair failed: ${err.message}. Raw prefix: ${text.substring(0, 150)}...`);
    }
  }
}

// MISTRAL API CONNECTION LAYER
async function callMistralAPI(messages, maxTokens = 3500, jsonMode = true, stepName = 'stage') {
  // Always retrieve the latest active rotated key
  const rotationResp = await fetch('api.php?action=get_key');
  const rotationData = await rotationResp.json();
  
  if (rotationData.error) {
    throw new Error('Key pool exhausted: ' + rotationData.error);
  }
  
  const apiKey = rotationData.key;
  const keyId = rotationData.id;
  
  log('ai', `Contacting Mistral AI on behalf of rotated key ID #${keyId} for stage: ${stepName.toUpperCase()}`);
  
  try {
    const payload = {
      model: 'devstral-2512',
      messages: messages,
      max_tokens: maxTokens
    };
    if (jsonMode) {
      payload.response_format = { type: 'json_object' };
    }
    
    const response = await fetch('https://api.mistral.ai/v1/chat/completions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + apiKey
      },
      body: JSON.stringify(payload)
    });
    
    if (!response.ok) {
      const errPayload = await response.json().catch(() => ({}));
      // Track error count on database
      await fetch(`api.php?action=key_error&id=${keyId}`);
      throw new Error(`HTTP Error ${response.status}: ${errPayload.message || JSON.stringify(errPayload)}`);
    }
    
    const resData = await response.json();
    const tokens = resData.usage?.total_tokens || 0;
    
    // Log usage to database
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'record_usage',
        key_id: keyId,
        tokens: tokens,
        project_id: activeProjectId,
        step: stepName
      })
    });
    
    // Increment badges
    const currentTokensBadge = document.getElementById('totalTokensBadge');
    const existingVal = parseInt(currentTokensBadge.textContent.replace(/[^0-9]/g, '')) || 0;
    currentTokensBadge.textContent = `${existingVal + tokens} ${t('tokens_used')}`;
    
    return { content: resData.choices[0].message.content, tokens: tokens };
    
  } catch (error) {
    log('err', `Key ID #${keyId} error encountered: ${error.message}. Performing rotation automatic correction.`);
    await fetch(`api.php?action=key_error&id=${keyId}`);
    // Recurse / retry once automatically
    return callMistralAPI(messages, maxTokens, jsonMode, stepName);
  }
}

// -------------------------------------------------------------
// AUTONOMOUS AGENT: RUN BUILD PIPELINE & SELF-HEALING ENGINE
// -------------------------------------------------------------
async function launchAutonomousBuild() {
  if (isCurrentlyBuilding) return;
  
  const title = document.getElementById('briefTitle').value.trim();
  const who = document.getElementById('briefWho').value.trim();
  const target = document.getElementById('briefTarget').value.trim();
  const monetize = document.getElementById('briefMonetize').value.trim();
  
  if (!title || !who || !target || !monetize) {
    alert(t('alert_fill_brief'));
    return;
  }
  
  isCurrentlyBuilding = true;
  document.getElementById('launchBtn').disabled = true;
  renderPipelineStages();
  clearTerminalLogs();
  
  log('sys', '═══════════════════════════════════════════════════════════');
  log('sys', '🚀 AUTONOMOUS AI PIPELINE INITIATED — SELF-HEALING ENGINE ONLINE');
  log('sys', '═══════════════════════════════════════════════════════════');
  
  try {
    // Stage 1: Key validation
    updateStageStatus('key_check', 'active');
    setProgress(5, t('progress_key_check'));
    const keysCheck = await fetch('api.php?action=get_data').then(res => res.json());
    const activeKeysCount = keysCheck.keys?.filter(k => k.is_active === 1).length || 0;
    if (activeKeysCount === 0) {
      throw new Error(t('empty_keys'));
    }
    log('ok', `API Key pool checked: ${activeKeysCount} active key(s) ready to host pipeline.`);
    updateStageStatus('key_check', 'completed');
    
    // Stage 2: Brief digest
    updateStageStatus('brief_digest', 'active');
    setProgress(10, t('progress_digest'));
    const siteType = document.getElementById('siteType').value;
    const outputLang = document.getElementById('outputLang').value;
    const cssFramework = document.getElementById('cssFramework').value;
    const techStack = document.getElementById('techStack').value;
    
    const projectSetupPayload = {
      action: 'create_project',
      title: title,
      brief: JSON.stringify({ who, target, monetize, tech_stack: techStack }),
      site_type: siteType,
      output_lang: outputLang,
      css_framework: cssFramework
    };
    
    const projResponse = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(projectSetupPayload)
    }).then(res => res.json());
    
    if (projResponse.error) throw new Error(projResponse.error);
    
    activeProjectId = projResponse.id;
    activeProjectFolder = projResponse.folder;
    activeProjectSlug = projResponse.slug;
    
    log('ok', `Database entry generated. Project ID: #${activeProjectId}. Workspace target: ${activeProjectFolder}`);
    updateStageStatus('brief_digest', 'completed');
    
    // Stage: Niche Research
    updateStageStatus('niche_research', 'active');
    setProgress(15, t('progress_research'));
    
    const researchMessages = [
      {
        role: 'system',
        content: `You are an expert business strategy analyst and competitor research agent. Respond ONLY in valid JSON. No markdown, no conversational text.
        Your JSON must follow this exact format:
        {
          "market_segment": "Detailed explanation of the target market segment and opportunity",
          "competitors": [
            {"name": "Competitor Name", "strength": "What they do well", "weakness": "What they miss", "takeaway": "How we will beat them"}
          ],
          "essential_features": [
            {"feature": "Feature Name", "reason": "Why it is critical for this site type", "implementation_idea": "How it should look/work on a web page"}
          ],
          "copywriting_hooks": {
            "hero_headline": "Engaging high-converting headline in target language",
            "hero_subheadline": "Compelling subheadline that details value proposition",
            "trust_statement": "Short trust building tagline",
            "cta_text": "Strong action-oriented button text"
          }
        }
        Do competitor research for a ${siteType} web platform in language: ${outputLang}.`
      },
      {
        role: 'user',
        content: `Perform autonomous niche research based on this brief:
        - BRAND IDENTITY: ${who}
        - TARGET AUDIENCE: ${target}
        - GOAL / MONETIZATION: ${monetize}
        - SITE TYPE: ${siteType}
        - LANGUAGE CODE: ${outputLang}`
      }
    ];
    
    const researchResp = await callMistralAPI(researchMessages, 2500, true, 'niche_research');
    const activeNicheResearch = safeJsonParse(researchResp.content);
    log('ok', `Niche competitor research completed! Segment: ${activeNicheResearch.market_segment}`);
    log('ok', `Defined Copywriting headline: "${activeNicheResearch.copywriting_hooks.hero_headline}"`);
    updateStageStatus('niche_research', 'completed');
    
    // Stage 3: Architecture Generation
    updateStageStatus('arch_design', 'active');
    setProgress(22, t('progress_architecture'));
    
    const archMessages = [
      {
        role: 'system',
        content: `You are an expert full-stack system and cross-platform architect. Respond ONLY in valid JSON. No markdown, no conversational text.
        Based on the target business type, user brief, and selected tech stack directive, you must autonomously design the entire application structure.
        You are not restricted to PHP or HTML. If the stack is Node.js, you can specify .js, .html, or .json files. If it's Python, you can specify .py and HTML files. If it's a mobile mockup target, you can specify .kt (Kotlin), .xml (layouts), or .swift UI files. If it's a pure web app, choose the appropriate web extensions.
        You must decide exactly how many files are needed (between 2 to 7 files) to build a robust, premium, high-converting product.

        Your JSON response must strictly follow this exact schema:
        {
          "site_name": "Name of the application/project",
          "chosen_stack": "Detailed name of the stack used (e.g. 'Node.js Express + HTML5', 'Python Flask + CSS Grid', 'HTML5 Vanilla CSS', 'Android Kotlin Layout Mockup')",
          "site_concept": "Creative modern design system, layout, and branding theme details",
          "pages": [
            {"filename": "main_filename.ext", "title": "File/Page Title", "desc": "Extremely detailed description of the file's role, UI structures, and functional components"}
          ],
          "colors": { "primary": "#hex", "secondary": "#hex", "accent": "#hex", "background": "#hex" },
          "layout_instructions": "Guidelines for consistent spacing, micro-animations, glassmorphism elements, or platform-specific layout constraints"
        }`
      },
      {
        role: 'user',
        content: `Generate project architecture based on this brief, tech stack preference, and niche research:
        - BRAND IDENTITY: ${who}
        - TARGET AUDIENCE: ${target}
        - MONETIZATION/GOAL: ${monetize}
        - BUSINESS TYPE: ${siteType}
        - LANGUAGE CODE: ${outputLang}
        - SELECTED CSS FRAMEWORK (If applicable): ${cssFramework}
        - TECH STACK DIRECTIVE: ${techStack === 'auto' ? 'Pure AI Autonomy (determine the absolute best stack yourself!)' : techStack}
        
        NICHE RESEARCH ANALYSIS:
        ${JSON.stringify(activeNicheResearch, null, 2)}`
      }
    ];
    
    const archResponse = await callMistralAPI(archMessages, 2500, true, 'architecture');
    activeSiteArchitecture = safeJsonParse(archResponse.content);
    log('ok', `AI Web Architecture crafted! Chosen Stack: "${activeSiteArchitecture.chosen_stack}" - ${activeSiteArchitecture.pages.length} files structured.`);
    
    // Store structural architecture JSON in DB
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'update_project',
        id: activeProjectId,
        arch_json: JSON.stringify(activeSiteArchitecture)
      })
    });
    
    updateStageStatus('arch_design', 'completed');
    
    // Stage 4: CSS Stylesheet / Styling Assets Generation
    updateStageStatus('css_shared', 'active');
    setProgress(30, t('progress_css'));
    let cssCode = '';
    let styleFilename = 'style.css';
    
    const isMobileTarget = techStack === 'mobile' || (activeSiteArchitecture.chosen_stack && activeSiteArchitecture.chosen_stack.toLowerCase().includes('mobile'));
    
    if (isMobileTarget) {
      styleFilename = 'colors.xml';
      cssCode = '<' + '?xml version="1.0" encoding="utf-8"?' + `>
<resources>
    <color name="colorPrimary">${activeSiteArchitecture.colors.primary}</color>
    <color name="colorSecondary">${activeSiteArchitecture.colors.secondary}</color>
    <color name="colorAccent">${activeSiteArchitecture.colors.accent}</color>
    <color name="colorBackground">${activeSiteArchitecture.colors.background || '#121212'}</color>
</resources>`;
    } else {
      if (cssFramework === 'vanilla' || techStack === 'auto' || techStack === 'html_css_js') {
        const cssMessages = [
          {
            role: 'system',
            content: 'You are a professional CSS designer. Write highly modern, premium, grid-focused responsive CSS layouts. Support variables, micro-animations, glassmorphism card templates, custom scrollbars, and Outfit/Tajawal fonts. Return ONLY valid JSON: {"css": "all compiled css code"}'
          },
          {
            role: 'user',
            content: `Write modern styling for web concept: ${activeSiteArchitecture.site_concept}.
            Colors: Primary ${activeSiteArchitecture.colors.primary}, Secondary ${activeSiteArchitecture.colors.secondary}, Accent ${activeSiteArchitecture.colors.accent}.
            Must look extremely premium, high design standard, animations, hover effects, beautiful layouts.`
          }
        ];
        const cssResp = await callMistralAPI(cssMessages, 3500, true, 'shared_css');
        let cssData;
        try {
          cssData = safeJsonParse(cssResp.content);
        } catch (e) {
          log('warn', `CSS JSON parsing failed: ${e.message}. Attempting regex extraction fallback.`);
          let rawText = cleanRawJsonText(cssResp.content);
          let match = rawText.match(/"css"\s*:\s*"([\s\S]*)/i);
          if (match) {
            let extracted = match[1];
            let unescaped = extracted
              .replace(/\\"/g, '"')
              .replace(/\\'/g, "'")
              .replace(/\\n/g, '\n')
              .replace(/\\r/g, '\r')
              .replace(/\\t/g, '\t')
              .replace(/\\\\/g, '\\');
            unescaped = unescaped.replace(/"\s*}\s*$/, '').replace(/"\s*$/, '');
            cssData = { css: unescaped };
          } else {
            cssData = { css: rawText };
          }
        }
        cssCode = cssData.css || '';
      } else {
        cssCode = `/* Styling additions */
:root {
  --primary: ${activeSiteArchitecture.colors.primary};
  --secondary: ${activeSiteArchitecture.colors.secondary};
  --accent: ${activeSiteArchitecture.colors.accent};
}`;
      }
    }
    
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_file',
        path: `${activeProjectFolder}/${styleFilename}`,
        content: cssCode
      })
    });
    log('ok', `Styles framework ${styleFilename} persisted inside sandbox!`);
    updateStageStatus('css_shared', 'completed');
    
    // Stage 5: Config Shared File Setup / Dynamic Configurations
    updateStageStatus('config_shared', 'active');
    setProgress(35, t('progress_config'));
    
    let configFilename = 'config.json';
    let configContent = '';
    
    const determinedStack = (activeSiteArchitecture.chosen_stack || techStack).toLowerCase();
    
    if (determinedStack.includes('php')) {
      configFilename = 'config.php';
      configContent = `<?php
define('SITE_NAME', ${JSON.stringify(activeSiteArchitecture.site_name)});
define('SITE_CONCEPT', ${JSON.stringify(activeSiteArchitecture.site_concept)});
define('COLOR_PRIMARY', ${JSON.stringify(activeSiteArchitecture.colors.primary)});
define('COLOR_SECONDARY', ${JSON.stringify(activeSiteArchitecture.colors.secondary)});
define('COLOR_ACCENT', ${JSON.stringify(activeSiteArchitecture.colors.accent)});
define('CSS_FRAMEWORK', ${JSON.stringify(cssFramework)});
define('LANG_CODE', ${JSON.stringify(outputLang)});
define('DEBUG_MODE', isset($_GET['debug']));
?>`;
    } else if (determinedStack.includes('node') || determinedStack.includes('js') || determinedStack.includes('express')) {
      configFilename = 'config.json';
      configContent = JSON.stringify({
        site_name: activeSiteArchitecture.site_name,
        site_concept: activeSiteArchitecture.site_concept,
        colors: activeSiteArchitecture.colors,
        lang_code: outputLang,
        css_framework: cssFramework,
        debug_mode: true
      }, null, 2);
      
      const pkgContent = JSON.stringify({
        name: activeProjectSlug,
        version: "1.0.0",
        description: activeSiteArchitecture.site_concept,
        main: "server.js",
        dependencies: {
          "express": "^4.19.2",
          "dotenv": "^16.4.5",
          "cors": "^2.8.5"
        }
      }, null, 2);
      
      await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save_file',
          path: `${activeProjectFolder}/package.json`,
          content: pkgContent
        })
      });
      log('ok', `Node.js project manifest package.json written successfully.`);
      
    } else if (determinedStack.includes('python') || determinedStack.includes('flask') || determinedStack.includes('fastapi')) {
      configFilename = 'config.json';
      configContent = JSON.stringify({
        site_name: activeSiteArchitecture.site_name,
        site_concept: activeSiteArchitecture.site_concept,
        colors: activeSiteArchitecture.colors,
        lang_code: outputLang,
        debug_mode: true
      }, null, 2);
      
      const reqContent = `flask>=3.0.0
fastapi>=0.110.0
uvicorn>=0.28.0
jinja2>=3.1.0
requests>=2.31.0
`;
      await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save_file',
          path: `${activeProjectFolder}/requirements.txt`,
          content: reqContent
        })
      });
      log('ok', `Python runtime dependencies requirements.txt written successfully.`);
    } else {
      configFilename = 'config.json';
      configContent = JSON.stringify({
        site_name: activeSiteArchitecture.site_name,
        site_concept: activeSiteArchitecture.site_concept,
        colors: activeSiteArchitecture.colors,
        lang_code: outputLang,
        css_framework: cssFramework
      }, null, 2);
    }
    
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_file',
        path: `${activeProjectFolder}/${configFilename}`,
        content: configContent
      })
    });
    log('ok', `Project shared configuration ${configFilename} persisted successfully.`);
    updateStageStatus('config_shared', 'completed');
    
    // Stage 6: Generation of Pages (Autonomous Loop)
    updateStageStatus('page_generation', 'active');
    setProgress(40, t('progress_generation'));
    
    const pagesList = activeSiteArchitecture.pages;
    const generatedPagesMemory = {};
    
    for (let i = 0; i < pagesList.length; i++) {
      const page = pagesList[i];
      const pageProgressPct = 40 + Math.round((i / pagesList.length) * 30);
      setProgress(pageProgressPct, t('progress_page') + page.filename + '...');
      
      const isPhpFile = page.filename.endsWith('.php');
      const isHtmlFile = page.filename.endsWith('.html');
      const isJsFile = page.filename.endsWith('.js');
      const isPyFile = page.filename.endsWith('.py');
      const isJsonFile = page.filename.endsWith('.json');
      const isXmlFile = page.filename.endsWith('.xml');
      const isSwiftFile = page.filename.endsWith('.swift');
      const isKotlinFile = page.filename.endsWith('.kt');
      
      const pageMessages = [
        {
          role: 'system',
          content: `You are an expert full-stack software developer and systems engineer.
          You must generate the complete, high-quality, production-ready source code for the requested file: "${page.filename}"
          The project utilizes the following technology stack/platform: "${activeSiteArchitecture.chosen_stack}"
          The target language of the UI and content must be: "${outputLang}"

          You must output valid JSON only in this exact format:
          {
            "filename": "${page.filename}",
            "code": "...Entire complete source code of the file..."
          }

          CRITICAL OUTPUT LIMITATIONS:
          - Output the ENTIRE, complete, fully closed source code file. Do NOT truncate or use placeholders (e.g. "// rest of code here"). Make it complete, fully functional, and well-designed.
          
          Specific File Instructions based on extension:
          ${isPhpFile ? `
          - It is a PHP file. Incorporate configuration by running: require_once 'config.php';
          - Link dynamically generated stylesheet exactly via: <link rel="stylesheet" href="style.css"> (do NOT write assets/css/style.css or other paths).
          - If Tailwind CSS framework is selected (${cssFramework} === 'tailwind'), you MUST inject the Play CDN script exactly: <script src="https://cdn.tailwindcss.com"><\/script> inside the <head> of the page.
          - If Output Language is Arabic ('ar'), set dir="rtl" on <html> and use Outfit/Tajawal fonts for design.
          - Ensure closing tags </body> and </html> are present.
          - Make it high-impact, premium glassmorphism dark theme, interactive cards, grid layouts.` : ''}
          ${isHtmlFile ? `
          - It is a standard HTML5 file.
          - Link dynamically generated stylesheet exactly via: <link rel="stylesheet" href="style.css">.
          - If Tailwind CSS framework is selected (${cssFramework} === 'tailwind'), you MUST inject the Play CDN script exactly: <script src="https://cdn.tailwindcss.com"><\/script> inside the <head> of the page.
          - If Output Language is Arabic ('ar'), set dir="rtl" on <html> and use Outfit/Tajawal fonts.
          - Make it high-impact, premium glassmorphism dark theme, interactive cards, grid layouts.` : ''}
          ${isJsFile ? `
          - It is a JavaScript file (Node.js/Express, custom router, or client-side ES6 JS). Write pure modern JavaScript.
          - If Express backend server, design elegant routes, middleware, robust request handling, static folder hosting, and serve files cleanly. Include complete, functional server logic.` : ''}
          ${isPyFile ? `
          - It is a Python file (Flask app, FastAPI routes, or script). Write clean, PEP8 compliant, functional Python code.
          - Ensure all necessary module imports are included, robust error handling is implemented, and the development server initiates cleanly on host '0.0.0.0' or '127.0.0.1'.` : ''}
          ${isJsonFile ? `
          - It is a JSON config manifest or package setup. Return valid JSON syntax. Do NOT write any code other than the JSON block itself inside the 'code' parameter.` : ''}
          ${isXmlFile ? `
          - It is an XML layout or resource file. Write standard XML code.` : ''}
          ${isSwiftFile ? `
          - It is an iOS Swift UI view or code file. Write clean, complete Swift UI declarations for premium mobile mockup layouts.` : ''}
          ${isKotlinFile ? `
          - It is an Android Kotlin class or Activity file. Write complete Kotlin syntax for premium mobile mockup screens.` : ''}

          Context and Creative Hooks to Integrate:
          - Use these copywriting hooks specifically in your text content: ${JSON.stringify(activeNicheResearch.copywriting_hooks)}.
          - Make sure to integrate these essential competitor-beat features: ${JSON.stringify(activeNicheResearch.essential_features)}.
          - Design style standard: premium glassmorphism themes, interactive cards, smooth modern grids, and micro-animations.`
        },
        {
          role: 'user',
          content: `Build the complete source code for "${page.filename}".
          - Title: ${page.title}
          - Purpose: ${page.desc}
          - Brand Identity / Brief: ${who}
          - Target Audience: ${target}
          - CSS Framework selected: ${cssFramework} (If applicable)`
        }
      ];
      
      let pageData;
      let generateRetry = 0;
      const maxRetries = 3;
      
      while (generateRetry < maxRetries) {
        try {
          const pageResp = await callMistralAPI(pageMessages, 3500, true, `page_${page.filename}`);
          pageData = safeJsonParse(pageResp.content);
          if (!pageData.code) throw new Error("JSON structure did not contain code parameter.");
          break; // Success!
        } catch (err) {
          generateRetry++;
          log('warn', `Generation attempt #${generateRetry} for ${page.filename} failed: ${err.message}. Retrying...`);
        }
      }
      
      if (!pageData) {
        throw new Error(`Critical: Failed to generate source code for ${page.filename} after maximum attempts.`);
      }
      
      generatedPagesMemory[page.filename] = pageData.code;
      log('write', `Successfully generated page structure in memory: ${page.filename} (${pageData.code.length} characters)`);
    }
    
    updateStageStatus('page_generation', 'completed');
    
    // Inline Debug Injection (merged from standalone step)
    log('sys', 'Injecting interactive local server debugger inside pages...');
    for (const [fname, code] of Object.entries(generatedPagesMemory)) {
      const isPhpFile = fname.endsWith('.php');
      const isHtmlFile = fname.endsWith('.html');
      
      if ((isPhpFile || isHtmlFile) && code.includes('</body>')) {
        const debuggerCode = isPhpFile ? `
        \x3c?php if(isset(\$_GET['debug'])): ?\x3e
        <div id="autocoder-debug-pane" style="position:fixed;bottom:0;left:0;right:0;background:#05070a;border-top:2px solid var(--accent, #00e5c3);color:#e2e8f0;padding:12px 20px;font-family:monospace;font-size:11px;z-index:99999;box-shadow:0 -10px 30px rgba(0,0,0,0.8);max-height:220px;overflow-y:auto;text-align:left;direction:ltr;">
          <div style="display:flex;justify-content:between;align-items:center;margin-bottom:8px;border-bottom:1px solid #1e293b;padding-bottom:6px;">
            <strong style="color:var(--accent, #00e5c3);">⚙️ AUTO-DEBUG MONITOR &mdash; ${fname}</strong>
            <span style="background:#1e293b;padding:2px 8px;border-radius:4px;font-size:10px;">V3 Sandbox Environment</span>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <strong>PHP Context Info:</strong><br>
              - PHP Version: \x3c?php echo PHP_VERSION; ?\x3e<br>
              - Memory Usage: \x3c?php echo round(memory_get_usage() / 1024 / 1024, 2); ?\x3e MB<br>
              - Execution Time: \x3c?php echo round(microtime(true) - \$_SERVER["REQUEST_TIME_FLOAT"], 4); ?\x3es
            </div>
            <div>
              <strong>Server Environment Variables:</strong><br>
              - Request Method: \x3c?php echo \$_SERVER['REQUEST_METHOD']; ?\x3e<br>
              - User Agent: \x3c?php echo htmlspecialchars(\$_SERVER['HTTP_USER_AGENT']); ?\x3e<br>
              - Sandbox Workspace Path: \x3c?php echo htmlspecialchars(dirname(__FILE__)); ?\x3e
            </div>
          </div>
        </div>
        \x3c?php endif; ?\x3e
        ` : `
        <div id="autocoder-debug-pane" style="position:fixed;bottom:0;left:0;right:0;background:#05070a;border-top:2px solid #00e5c3;color:#e2e8f0;padding:12px 20px;font-family:monospace;font-size:11px;z-index:99999;box-shadow:0 -10px 30px rgba(0,0,0,0.8);max-height:220px;overflow-y:auto;text-align:left;direction:ltr;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;border-bottom:1px solid #1e293b;padding-bottom:6px;">
            <strong style="color:#00e5c3;">⚙️ AUTO-DEBUG MONITOR &mdash; ${fname}</strong>
            <span style="background:#1e293b;padding:2px 8px;border-radius:4px;font-size:10px;">V3 Sandbox Environment</span>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <strong>Client Context Info:</strong><br>
              - Client URL: <span id="dbg-client-url"></span><br>
              - Screen Resolution: <span id="dbg-screen-res"></span><br>
              - Platform Protocol: <span id="dbg-protocol"></span>
            </div>
            <div>
              <strong>Browser Environment:</strong><br>
              - Navigator: <span id="dbg-navigator"></span><br>
              - Language Settings: <span id="dbg-lang"></span>
            </div>
          </div>
          <script>
            document.getElementById('dbg-client-url').textContent = window.location.href;
            document.getElementById('dbg-screen-res').textContent = window.screen.width + 'x' + window.screen.height;
            document.getElementById('dbg-protocol').textContent = window.location.protocol;
            document.getElementById('dbg-navigator').textContent = navigator.userAgent.substring(0, 70) + '...';
            document.getElementById('dbg-lang').textContent = navigator.language;
          <\/script>
        </div>
        `;
        generatedPagesMemory[fname] = code.replace('</body>', `${debuggerCode}\n</body>`);
      }
    }
    log('ok', 'AI Debug toolset successfully injected to web-based page footer modules.');
    
    // Stage 7: Files Persistence
    updateStageStatus('file_persistance', 'active');
    setProgress(75, t('progress_persistence'));
    
    let fileCount = 0;
    for (const [fname, content] of Object.entries(generatedPagesMemory)) {
      const fileSavePayload = {
        action: 'save_file',
        path: `${activeProjectFolder}/${fname}`,
        content: content
      };
      
      const saveResp = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(fileSavePayload)
      }).then(res => res.json());
      
      if (saveResp.error) {
        log('err', `Error writing file ${fname} to target: ${saveResp.error}`);
      } else {
        fileCount++;
        log('write', `File successfully compiled: ${fname} (${saveResp.bytes} bytes saved)`);
      }
    }
    updateStageStatus('file_persistance', 'completed');
    
    // Stage 8: AI QA Code Reflection & Stage 9: Self-Healing
    updateStageStatus('ai_qa_reflection', 'active');
    setProgress(80, t('progress_qa'));
    
    let baselinePages = JSON.parse(JSON.stringify(generatedPagesMemory));
    let baselineScore = 0;
    let issuesDetected = [];
    let iteration = 1;
    const maxIterations = 5;
    
    // Define the dynamic, stack-aware QA prompt
    const qaSystemPrompt = `You are an expert senior code QA and automation tester.
    Your task is to inspect the ENTIRE set of generated application files and evaluate their operational readiness.
    The project utilizes the following technology stack/platform: "${activeSiteArchitecture.chosen_stack}"
    The generated files are: ${pagesList.map(p => p.filename).join(', ')}.

    You must inspect the files based on the stack parameters:
    1. Truncation or incomplete files:
       - For web/HTML/PHP files, does every file have proper closing tags (e.g., </body>, </html>)? If a file ends abruptly or is incomplete, raise a 'high' severity issue with 'solution_code' containing the full, completed version of the file.
       - For other code languages (Python, Javascript, Kotlin, Swift, XML, JSON), verify there are no missing curly braces, unclosed quotes, or incomplete statements.
    2. Asset loading & Framework imports:
       - For HTML/PHP web files: If the CSS framework is 'tailwind', verify that <script src="https://cdn.tailwindcss.com"><\/script> is loaded in the <head>. If missing, raise a 'high' severity issue with the exact solution code. If the framework is 'vanilla', verify that <link rel="stylesheet" href="style.css"> is loaded. If there's an incorrect stylesheet path, flag it.
       - For Node.js files (package.json/server.js): Ensure valid express imports and routing.
       - For Python Flask/FastAPI files: Ensure clean imports, routing, and host configuration.
    3. Broken internal links & references:
       - Ensure that links, API routes, file imports, or layout components map exactly to the generated files: ${pagesList.map(p => p.filename).join(', ')}. If there are external, missing, or mismatched links/runtimes, raise a 'medium' severity issue.
    4. Syntax & Runtime Errors:
       - Ensure there are no undefined constants, broken loops, or mismatched scopes.
    5. Aesthetics & UX Standards:
       - Score the project lower if it has boring, unstyled layouts. Ensure it follows modern, responsive styling practices.

    Return ONLY valid JSON format:
    {
      "issues_detected": [
        {"filename": "main_file.ext", "issue": "detailed issue description", "severity": "high|medium|low", "solution_code": "...exact complete replacement code or fix for this file..."}
      ],
      "qa_score": 95,
      "summary": "Overall evaluation summary"
    }`;
    
    while (iteration <= maxIterations) {
      log('sys', `═══════════════════════════════════════════════════════════`);
      log('sys', `🔍 QA OPTIMIZATION LOOP - ITERATION #${iteration} / ${maxIterations}`);
      log('sys', `═══════════════════════════════════════════════════════════`);
      
      let localLintIssues = [];
      // Perform server-side linting verification for each page
      for (const [fname, code] of Object.entries(baselinePages)) {
        try {
          const lintResp = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'lint_file',
              path: `${activeProjectFolder}/${fname}`
            })
          }).then(res => res.json());
          
          if (lintResp.syntax_ok === false) {
            log('warn', `🚨 Linter error in ${fname}: ${lintResp.message}`);
            localLintIssues.push({
              filename: fname,
              issue: `Syntax Lint Error: ${lintResp.message}`,
              severity: 'high',
              solution_code: 'Correct the syntax mismatch, unclosed statements, or structural syntax errors.'
            });
          } else {
            log('ok', `✓ Linter check passed for: ${fname}`);
          }
        } catch (e) {
          log('sys', `Linter execution skipped for ${fname}: ${e.message}`);
        }
      }
      
      // Perform QA Reflection on baselinePages
      const qaMessages = [
        {
          role: 'system',
          content: qaSystemPrompt
        },
        {
          role: 'user',
          content: `Inspect these files generated for concept: ${activeSiteArchitecture.site_concept}.
          
          ${Object.entries(baselinePages).map(([f, code]) => `
          --- FILE: ${f} ---
          ${code}
          --- END ---
          `).join('\n')}`
        }
      ];
      
      const qaResp = await callMistralAPI(qaMessages, 3000, true, `reflection_iter_${iteration}`);
      const qaReport = safeJsonParse(qaResp.content);
      
      // Merge local linter issues with AI issues
      let mergedIssues = [...localLintIssues, ...(qaReport.issues_detected || [])];
      let adjustedScore = qaReport.qa_score;
      if (localLintIssues.length > 0) {
        adjustedScore = Math.max(10, qaReport.qa_score - (localLintIssues.length * 25)); // severe penalty for syntax errors
      }
      
      if (iteration === 1) {
        baselineScore = adjustedScore;
        issuesDetected = mergedIssues;
        log('ok', `Initial QA Inspection complete. Baseline Score: ${baselineScore}/100. Issues detected: ${issuesDetected.length}`);
      } else {
        log('ok', `QA iteration #${iteration} score: ${adjustedScore}/100. Remaining issues: ${mergedIssues.length}`);
      }
      
      // Update database with latest score
      await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'update_project',
          id: activeProjectId,
          qa_score: adjustedScore,
          file_count: fileCount
        })
      });
      
      // If we achieved score >= 95 or there are no issues, we are done!
      if (mergedIssues.length === 0 || adjustedScore >= 95) {
        baselineScore = adjustedScore;
        issuesDetected = mergedIssues;
        log('ok', `🎉 High Quality Metric Achieved: Score ${adjustedScore}/100! Exiting QA loop.`);
        break;
      }
      
      // Activate Self-Healing stage visual indicators
      updateStageStatus('self_healing', 'active');
      document.getElementById('healingBanner').style.display = 'flex';
      log('heal', `⚠️ Self-Healing Iteration #${iteration} Active! Resolving ${mergedIssues.length} critical QA tickets autonomously.`);
      
      let workPages = JSON.parse(JSON.stringify(baselinePages));
      
      for (const issue of mergedIssues) {
        setProgress(80 + iteration, t('progress_healing') + issue.filename + ` (${t('iteration')} #${iteration})...`);
        document.getElementById('healingStatus').textContent = t('correcting') + ': ' + issue.issue + ' ' + t('in_file') + ' ' + issue.filename + ` (${t('pass')} ${iteration}/${maxIterations})`;
        
        log('heal', `Self-Healing code block replacement for "${issue.filename}" due to: ${issue.issue}`);
        
        const healMessages = [
          {
            role: 'system',
            content: `You are an automated self-healing developer system.
            You are provided a file containing errors, a description of the error, and a proposed fix.
            Rewrite the code to fix the error while keeping the rest of the functional logic.
            Output ONLY valid JSON:
            {
              "filename": "${issue.filename}",
              "fixed_code": "...Complete updated functional code..."
            }`
          },
          {
            role: 'user',
            content: `
            FILE TO FIX: ${issue.filename}
            ERROR DESCRIPTION: ${issue.issue}
            PROPOSED FIX: ${issue.solution_code}
            EXISTING CODE CONTENT:
            ${workPages[issue.filename] || ''}`
          }
        ];
        
        try {
          const healResp = await callMistralAPI(healMessages, 3500, true, `healing_${issue.filename}_iter_${iteration}`);
          const healedData = safeJsonParse(healResp.content);
          if (healedData.fixed_code) {
            workPages[issue.filename] = healedData.fixed_code;
            log('ok', `Hypothesis generated successfully for: ${issue.filename}`);
          }
        } catch (healErr) {
          log('err', `Self-healing fix failed for ${issue.filename}: ${healErr.message}`);
        }
      }
      
      // Re-evaluate workPages using QA model
      log('sys', `🧪 Testing updated code quality hypothesis...`);
      
      // Save test files first temporarily so that lint_file checks the new hypothesis
      for (const [fname, content] of Object.entries(workPages)) {
        await fetch('api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'save_file',
            path: `${activeProjectFolder}/${fname}`,
            content: content
          })
        });
      }

      let testLintIssues = [];
      for (const [fname, code] of Object.entries(workPages)) {
        try {
          const lintResp = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'lint_file',
              path: `${activeProjectFolder}/${fname}`
            })
          }).then(res => res.json());
          
          if (lintResp.syntax_ok === false) {
            log('warn', `🚨 Linter error in hypothesis for ${fname}: ${lintResp.message}`);
            testLintIssues.push({
              filename: fname,
              issue: `Syntax Lint Error: ${lintResp.message}`,
              severity: 'high',
              solution_code: 'Correct the syntax mismatch, unclosed statements, or structural syntax errors.'
            });
          }
        } catch (e) {
          log('sys', `Linter execution skipped for ${fname}: ${e.message}`);
        }
      }
      
      const testMessages = [
        {
          role: 'system',
          content: qaSystemPrompt
        },
        {
          role: 'user',
          content: `Inspect these files generated for concept: ${activeSiteArchitecture.site_concept}.
          
          ${Object.entries(workPages).map(([f, code]) => `
          --- FILE: ${f} ---
          ${code}
          --- END ---
          `).join('\n')}`
        }
      ];
      
      const testResp = await callMistralAPI(testMessages, 3000, true, `reflection_test_iter_${iteration}`);
      const testReport = safeJsonParse(testResp.content);
      
      let testAdjustedScore = testReport.qa_score;
      if (testLintIssues.length > 0) {
        testAdjustedScore = Math.max(10, testReport.qa_score - (testLintIssues.length * 25));
      }
      
      // Compare scores
      if (testAdjustedScore > baselineScore) {
        const diff = testAdjustedScore - baselineScore;
        log('ok', `📈 SUCCESS: QA Score optimized from ${baselineScore} to ${testAdjustedScore} (+${diff})! Committing changes.`);
        
        // Save the improved files in memory
        baselinePages = JSON.parse(JSON.stringify(workPages));
        generatedPagesMemory = JSON.parse(JSON.stringify(workPages));
        baselineScore = testAdjustedScore;
        issuesDetected = [...testLintIssues, ...(testReport.issues_detected || [])];
        
        log('ok', `Overwritten baseline files on disk with optimized versions.`);
      } else {
        log('warn', `📉 ROLLBACK: QA Score is ${testAdjustedScore}/100, which did not improve baseline of ${baselineScore}/100. Discarding changes.`);
        // Restore disk baseline
        for (const [fname, content] of Object.entries(baselinePages)) {
          await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'save_file',
              path: `${activeProjectFolder}/${fname}`,
              content: content
            })
          });
        }
      }
      
      iteration++;
    }
    
    // Finalize Stages 9 & 10
    document.getElementById('healingBanner').style.display = 'none';
    updateStageStatus('ai_qa_reflection', 'completed');
    updateStageStatus('self_healing', 'completed');
    
    log('ok', `Autonomous Healing Cycle finished. Final Site QA Score: ${baselineScore}/100.`);
    
    // Stage 11: SEO & Project Documentation
    updateStageStatus('seo_engine', 'active');
    setProgress(95, t('progress_seo'));
    
    const docMessages = [
      {
        role: 'system',
        content: `You are an expert technical writer and software architect. Write a gorgeous, professional README.md for the generated project.
        It must contain:
        - A stunning header with the project title and a catchy branding tagline in the target language.
        - Detailed chosen technology stack and why it was selected.
        - Installation and setup instructions.
        - Directory/files structure breakdown showing what each file does.
        - Competitor strategy summary.
        - Return ONLY valid JSON format:
        {
          "readme": "...Complete markdown content for README.md..."
        }`
      },
      {
        role: 'user',
        content: `Generate a README.md based on:
        - Project Title: ${activeSiteArchitecture.site_name}
        - Stack: ${activeSiteArchitecture.chosen_stack}
        - Concept: ${activeSiteArchitecture.site_concept}
        - Files list: ${JSON.stringify(pagesList)}
        - Branding Brief: ${who}
        - Competitor Analysis: ${JSON.stringify(activeNicheResearch.competitors)}
        - Target Language: ${outputLang}`
      }
    ];

    let readmeContent = '';
    try {
      const docResp = await callMistralAPI(docMessages, 2500, true, 'readme_doc');
      const docData = safeJsonParse(docResp.content);
      readmeContent = docData.readme || '';
    } catch(err) {
      log('warn', `Mistral README generation failed: ${err.message}. Generating fallback documentation.`);
      readmeContent = `# ${activeSiteArchitecture.site_name}\n\nGenerated by AutoCoder V3.\n- Tech Stack: ${activeSiteArchitecture.chosen_stack}\n- Concept: ${activeSiteArchitecture.site_concept}`;
    }

    // Save README.md
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_file',
        path: `${activeProjectFolder}/README.md`,
        content: readmeContent
      })
    });
    log('ok', `README.md documentation generated successfully!`);

    // Save architecture_manifest.json
    const archManifest = JSON.stringify({
      project_name: activeSiteArchitecture.site_name,
      chosen_stack: activeSiteArchitecture.chosen_stack,
      concept: activeSiteArchitecture.site_concept,
      colors: activeSiteArchitecture.colors,
      layout_instructions: activeSiteArchitecture.layout_instructions,
      files: pagesList,
      created_at: new Date().toISOString()
    }, null, 2);

    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_file',
        path: `${activeProjectFolder}/architecture_manifest.json`,
        content: archManifest
      })
    });
    log('ok', `architecture_manifest.json metadata persisted.`);

    const isMobileStack = determinedStack.includes('mobile') || determinedStack.includes('kotlin') || determinedStack.includes('swift');

    if (!isMobileStack) {
      // Generate web-specific SEO files
      const robotsTxt = `User-agent: *
Disallow: /config.php
Disallow: /style.css
Sitemap: http://\x3c?php echo \$_SERVER['HTTP_HOST']; ?\x3e/${activeProjectFolder}/sitemap.xml`;

      const sitemapXml = '<' + '?xml version="1.0" encoding="UTF-8"?' + '>\n' + `
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  ${pagesList.filter(p => p.filename.endsWith('.php') || p.filename.endsWith('.html')).map(p => `
  <url>
    <loc>http://\x3c?php echo \$_SERVER['HTTP_HOST']; ?\x3e/${activeProjectFolder}/${p.filename}</loc>
    <lastmod>${new Date().toISOString().split('T')[0]}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>${p.filename === 'index.php' ? '1.0' : '0.8'}</priority>
  </url>
  `).join('')}
</urlset>`;

    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_file',
        path: `${activeProjectFolder}/robots.txt`,
        content: robotsTxt
      })
    });
    
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_file',
        path: `${activeProjectFolder}/sitemap.xml`,
        content: sitemapXml
      })
    });
    
    log('ok', `SEO Assets (robots.txt, sitemap.xml) packaged.`);
    updateStageStatus('seo_engine', 'completed');
    }
    
    // Stage 12: Launch / Deployment
    updateStageStatus('sandbox_publish', 'active');
    setProgress(100, t('progress_sandbox'));
    
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'update_project',
        id: activeProjectId,
        status: 'done'
      })
    });
    
    log('ok', '═══════════════════════════════════════════════════════════');
    log('ok', '🎉 DEPLOYMENT COMPLETE! LIVE PREVIEW ACTIVE');
    log('ok', `📁 Location: ${activeProjectFolder}`);
    log('ok', `🌐 Live URL: ${activeProjectFolder}/index.php?debug=1`);
    log('ok', '═══════════════════════════════════════════════════════════');
    
    updateStageStatus('sandbox_publish', 'completed');
    
    // Launch iframe preview
    loadProjectPreview(activeProjectFolder, pagesList.map(p => p.filename), activeProjectId);
    
  } catch (error) {
    log('err', `FATAL PIPELINE HALT: ${error.message}`);
    // Highlight active failed stage
    PIPELINE_STAGES.forEach(s => {
      const pill = document.getElementById(`step-${s.id}`);
      if (pill && pill.classList.contains('active')) {
        updateStageStatus(s.id, 'failed');
      }
    });
  } finally {
    isCurrentlyBuilding = false;
    document.getElementById('launchBtn').disabled = false;
  }
}

function setProgress(pct, labelText) {
  document.getElementById('pipelineProgressFill').style.width = `${pct}%`;
  document.getElementById('pipelineProgressPct').textContent = `${pct}%`;
  document.getElementById('pipelineProgressLabel').textContent = labelText;
}

// -------------------------------------------------------------
// LIVE PREVIEW SANDBOX CONTROLLER
// -------------------------------------------------------------
let previewPagesList = [];
let previewFolderRoot = '';
let currentPreviewProjectId = null;

function downloadProjectZip() {
  const id = currentPreviewProjectId || activeProjectId;
  if (id) {
    window.location.href = `download.php?id=${id}`;
  } else {
    alert("No active project to download!");
  }
}

function loadProjectPreview(folderPath, pages, projectId = null) {
  currentPreviewProjectId = projectId;
  const zipBtn = document.getElementById('downloadZipBtn');
  if (zipBtn) zipBtn.style.display = projectId ? 'inline-block' : 'none';

  previewFolderRoot = folderPath;
  previewPagesList = pages;
  
  const selector = document.getElementById('previewPageSelector');
  selector.innerHTML = pages.map(p => `<option value="${p}">${p}</option>`).join('');
  selector.style.display = 'inline-block';
  
  document.getElementById('previewUrlLabel').textContent = `${folderPath}/${pages[0]}`;
  
  // Set iframe src
  const iframe = document.getElementById('previewFrame');
  iframe.src = `${folderPath}/${pages[0]}?debug=1`;
  
  // Update Code Explorer Sidebar
  const fileListEl = document.getElementById('codeFileList');
  if (fileListEl) {
    fileListEl.innerHTML = pages.map((p, i) => 
      `<li><button class="file-item-btn ${i === 0 ? 'active' : ''}" onclick="selectExplorerFile('${p}', this)">📄 ${p}</button></li>`
    ).join('');
    
    // Auto-select first file
    if (pages.length > 0) {
      selectExplorerFile(pages[0]);
    }
  }
  
  // Update Project Strategy if activeSiteArchitecture is populated
  if (activeSiteArchitecture && projectId === activeProjectId) {
    document.getElementById('strategyTitle').textContent = activeSiteArchitecture.site_name || 'Project Strategy';
    document.getElementById('strategyDesc').textContent = activeSiteArchitecture.site_concept || '';
    
    // Colors
    if (activeSiteArchitecture.colors) {
      const colors = activeSiteArchitecture.colors;
      document.getElementById('strategyColors').innerHTML = Object.entries(colors).map(([name, hex]) => 
        `<div style="display:flex; flex-direction:column; align-items:center; gap:4px;">
          <div style="width:40px; height:40px; border-radius:50%; background:${hex}; border:1px solid rgba(255,255,255,0.1)"></div>
          <span style="font-size:0.7rem; color:var(--text-secondary);">${name}</span>
        </div>`
      ).join('');
    }
    
    // Files grid
    if (activeSiteArchitecture.pages) {
      document.getElementById('strategyFiles').innerHTML = activeSiteArchitecture.pages.map(p => 
        `<div style="background:var(--bg-dark); padding:12px; border-radius:var(--radius-sm); border:1px solid var(--border);">
          <div style="font-family:var(--font-mono); font-size:0.8rem; color:var(--accent); margin-bottom:4px;">📄 ${p.filename}</div>
          <div style="font-size:0.75rem; font-weight:700; margin-bottom:4px;">${p.title}</div>
          <div style="font-size:0.65rem; color:var(--text-secondary);">${p.desc}</div>
        </div>`
      ).join('');
    }
  }
}

function switchPreviewTab(tabId) {
  // Update active button
  document.querySelectorAll('.preview-tab-btn').forEach(btn => btn.classList.remove('active'));
  document.getElementById(`tabBtn${tabId.charAt(0).toUpperCase() + tabId.slice(1)}`).classList.add('active');
  
  // Update active content
  document.querySelectorAll('.preview-tab-content').forEach(content => {
    content.style.display = 'none';
    content.classList.remove('active');
  });
  
  const target = document.getElementById(`previewTab${tabId.charAt(0).toUpperCase() + tabId.slice(1)}`);
  if (target) {
    target.style.display = tabId === 'preview' ? 'flex' : 'block';
    target.classList.add('active');
  }
}

async function selectExplorerFile(filename, btnEl = null) {
  if (btnEl) {
    document.querySelectorAll('.file-item-btn').forEach(b => b.classList.remove('active'));
    btnEl.classList.add('active');
  } else {
    // try to find the button
    const buttons = document.querySelectorAll('.file-item-btn');
    buttons.forEach(b => {
      b.classList.remove('active');
      if (b.textContent.includes(filename)) b.classList.add('active');
    });
  }
  
  const contentEl = document.getElementById('codeViewerContent');
  contentEl.textContent = "Loading file contents...";
  
  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'read_file',
        path: `${previewFolderRoot}/${filename}`
      })
    }).then(r => r.json());
    
    if (res.error) {
      contentEl.textContent = `Error reading file: ${res.error}`;
    } else {
      contentEl.textContent = res.content || '// Empty file';
    }
  } catch (err) {
    contentEl.textContent = `Request failed: ${err.message}`;
  }
}

function changePreviewPage() {
  const page = document.getElementById('previewPageSelector').value;
  document.getElementById('previewUrlLabel').textContent = `${previewFolderRoot}/${page}`;
  document.getElementById('previewFrame').src = `${previewFolderRoot}/${page}?debug=1`;
}

function refreshPreviewFrame() {
  const iframe = document.getElementById('previewFrame');
  iframe.contentWindow.location.reload();
  log('sys', 'Live preview sandbox reloaded.');
}

// -------------------------------------------------------------
// DATA MANAGEMENT (KEYS, PROJECTS, STATS)
// -------------------------------------------------------------

async function addApiKey() {
  const label = document.getElementById('keyLabel').value.trim();
  const val = document.getElementById('keyVal').value.trim();
  
  if (!label || !val) {
    alert(t('alert_enter_key'));
    return;
  }
  
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'add_key',
      label: label,
      key: val
    })
  }).then(res => res.json());
  
  if (res.error) {
    alert(res.error);
  } else {
    document.getElementById('keyLabel').value = '';
    document.getElementById('keyVal').value = '';
    loadKeys();
    log('sys', `New API key [${label}] registered to rotate stack.`);
  }
}

async function testApiKey(keyVal) {
  log('sys', 'Running instant readiness checks on target key...');
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action: 'test_key',
      key: keyVal
    })
  }).then(res => res.json());
  
  if (res.code === 200) {
    alert(`Success! Key is active and operational. Status: ${res.status}`);
  } else {
    alert(`Error: API Key verification failed. HTTP Status ${res.code}`);
  }
}

async function deleteApiKey(id) {
  if (!confirm(t('alert_delete_key'))) return;
  await fetch(`api.php?action=delete_key&id=${id}`);
  loadKeys();
  log('sys', `API key ID #${id} deleted.`);
}

async function resetApiKeyErrors(id) {
  await fetch(`api.php?action=reset_key&id=${id}`);
  loadKeys();
  log('sys', `API key ID #${id} errors count reset to 0.`);
}

async function loadKeys() {
  const data = await fetch('api.php?action=get_data').then(res => res.json());
  const list = document.getElementById('keysList');
  
  const activeKeysCount = data.keys.filter(k => k.is_active === 1).length;
  document.getElementById('totalKeyBadge').textContent = `${activeKeysCount} ${t('active_keys')}`;
  document.getElementById('totalTokensBadge').textContent = `${data.stats.tokens_total} ${t('tokens_used')}`;
  
  if (!data.keys || data.keys.length === 0) {
    list.innerHTML = `<div class="empty-state">${t('empty_keys')}</div>`;
    return;
  }
  
  list.innerHTML = data.keys.map(k => `
    <div class="item-row">
      <div>
        <strong style="font-size:0.85rem;">${k.label}</strong>
        <div style="font-size:0.7rem; color:var(--text-secondary); font-family:var(--font-mono);">${k.key_masked}</div>
        <div style="font-size:0.65rem; color:${k.is_active ? 'var(--success)' : 'var(--error)'};">
          ${k.is_active ? '● ' + t('key_active') : '● ' + t('key_inactive')} (${t('errors')}: ${k.error_count})
        </div>
      </div>
      <div style="display:flex; gap:6px;">
        <button class="btn btn-outline" style="padding:4px 8px; font-size:0.7rem;" onclick="resetApiKeyErrors(${k.id})">↻ ${t('btn_reset')}</button>
        <button class="btn btn-danger" style="padding:4px 8px; font-size:0.7rem;" onclick="deleteApiKey(${k.id})">✗</button>
      </div>
    </div>
  `).join('');
}

async function loadProjects() {
  const data = await fetch('api.php?action=list_projects').then(res => res.json());
  const list = document.getElementById('projectsList');
  
  if (!data.projects || data.projects.length === 0) {
    list.innerHTML = `<div class="empty-state">${t('empty_projects')}</div>`;
    return;
  }
  
  list.innerHTML = data.projects.map(p => `
    <div class="item-row" style="flex-direction: column; align-items: stretch; gap: 8px;">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <strong style="font-size:0.9rem; color:var(--accent);">${p.title}</strong>
          <span style="font-size:0.7rem; background:var(--border); padding:2px 6px; border-radius:4px; margin-left:6px; text-transform:uppercase;">
            ${p.site_type}
          </span>
        </div>
        <span style="font-size:0.75rem; color:${p.status === 'done' ? 'var(--success)' : 'var(--warning)'}; font-weight:600;">
          ${p.status === 'done' ? '✓ ' + t('proj_ready') : '● ' + t('proj_building')}
        </span>
      </div>
      
      <div style="font-size:0.7rem; color:var(--text-secondary); font-family:var(--font-mono); display:flex; justify-content:space-between;">
        <span>${t('qa_rating')}: ${p.qa_score}/100</span>
        <span>${t('files')}: ${p.file_count}</span>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px; border-top:1px solid var(--border); padding-top:8px;">
        <span style="font-size:0.65rem; color:var(--text-muted);">${p.created_at}</span>
        <div style="display:flex; gap:6px;">
          <button class="btn btn-primary" style="padding:4px 8px; font-size:0.7rem;" onclick="loadPreviewFromHistory('${p.folder}', ${p.id})">${t('btn_open_sandbox')}</button>
          <button class="btn btn-danger" style="padding:4px 8px; font-size:0.7rem;" onclick="deleteProject(${p.id})">${t('btn_delete')}</button>
        </div>
      </div>
    </div>
  `).join('');
}

async function loadPreviewFromHistory(folderPath, projectId) {
  log('sys', `Loading historical project preview workspace: ${folderPath}`);
  const project = await fetch(`api.php?action=get_project&id=${projectId}`).then(res => res.json());
  if (project.project && project.project.arch_json) {
    const arch = JSON.parse(project.project.arch_json);
    const pages = arch.pages.map(p => p.filename);
    loadProjectPreview(folderPath, pages, projectId);
  } else {
    // Default fallback
    loadProjectPreview(folderPath, ['index.php'], projectId);
  }
}

async function deleteProject(id) {
  if (!confirm(t('alert_delete_project'))) return;
  await fetch(`api.php?action=delete_project&id=${id}`);
  loadProjects();
  log('sys', `Project ID #${id} deleted from databases & disk.`);
}

async function loadStats() {
  const data = await fetch('api.php?action=get_data').then(res => res.json());
  
  document.getElementById('statKeys').textContent = data.stats.keys_active;
  document.getElementById('statTokens').textContent = formatTokensNumber(data.stats.tokens_total);
  document.getElementById('statProjects').textContent = data.stats.projects_total;
  
  // Render Chart
  renderStatsChart(data.chart);
}

function formatTokensNumber(num) {
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
  if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
  return num;
}

let activeChartInstance = null;
function renderStatsChart(chartData) {
  const ctx = document.getElementById('tokensChart').getContext('2d');
  if (activeChartInstance) activeChartInstance.destroy();
  
  const labels = chartData.map(c => c.day);
  const values = chartData.map(c => c.total);
  
  activeChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Tokens Used',
        data: values,
        borderColor: '#00e5c3',
        backgroundColor: 'rgba(0, 229, 195, 0.05)',
        tension: 0.4,
        fill: true
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#4b5563', font: { size: 9 } } },
        y: { grid: { color: '#1e2530' }, ticks: { color: '#4b5563', font: { size: 9 } } }
      }
    }
  });
}

// Startup initializations
window.addEventListener('DOMContentLoaded', () => {
  renderPipelineStages();
  setUILang('en');
  // Load keys metadata count initially
  loadKeys();
});
