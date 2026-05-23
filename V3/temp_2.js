
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
       - For HTML/PHP web files: If the CSS framework is 'tailwind', verify that <script src="https://cdn.tailwindcss.com">