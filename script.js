console.log('script.js carregado!');

// Função auxiliar para escapar HTML
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}

document.addEventListener('DOMContentLoaded', function () {
  console.log('DOM pronto!');

  initThemeToggle();

  // Funcionalidade de upload de arquivo
  const fileInput = document.getElementById('anexoDocumento');
  const fileButton = document.querySelector('.file-upload-button');

  console.log('fileInput:', fileInput);
  console.log('fileButton:', fileButton);

  if (fileInput && fileButton) {
    fileButton.addEventListener('click', function (event) {
      event.preventDefault();
      fileInput.click();
    });

    // Detectar quando um arquivo é selecionado
    fileInput.addEventListener('change', function () {
      console.log('Change event disparado', this.files);
      
      const fileStatusContainer = document.getElementById('fileUploadStatus');
      console.log('fileStatusContainer:', fileStatusContainer);
      
      if (!fileStatusContainer) {
        console.error('fileUploadStatus container não encontrado!');
        return;
      }

      if (this.files && this.files.length > 0) {
        const file = this.files[0];
        const fileName = file.name;
        const fileSize = (file.size / 1024).toFixed(2); // Converter para KB
        
        console.log('Arquivo selecionado:', fileName, fileSize, 'KB');
        
        // Mostrar status do arquivo
        fileStatusContainer.innerHTML = `
          <div class="file-attached">
            <span class="file-icon">📄</span>
            <div class="file-info">
              <div class="file-name">${escapeHtml(fileName)}</div>
              <div class="file-size">${fileSize} KB</div>
            </div>
            <button type="button" class="file-remove-btn" aria-label="Remover arquivo">
              <i class="fas fa-times"></i>
            </button>
          </div>
        `;
        
        // Adicionar evento para remover arquivo
        const removeBtn = fileStatusContainer.querySelector('.file-remove-btn');
        if (removeBtn) {
          removeBtn.addEventListener('click', function (event) {
            event.preventDefault();
            fileInput.value = '';
            fileStatusContainer.innerHTML = '';
          });
        }
      } else {
        fileStatusContainer.innerHTML = '';
      }
    });
  } else {
    console.error('fileInput ou fileButton não encontrados!');
  }

  // Carregar e renderizar as vagas dinamicamente
  loadJobs();

  // Recarregar vagas quando a página fica visível (usuário volta à aba)
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      loadJobs();
    }
  });

  // Funcionalidade para os links "Enviar currículo" genérico
  const enviarCurriculoLinks = document.querySelectorAll('a[href="#formulario"]');
  enviarCurriculoLinks.forEach(link => {
    link.addEventListener('click', function (event) {
      event.preventDefault();
      
      const vagaSelect = document.getElementById('vaga');
      const formSection = document.getElementById('formulario');
      
      // Limpar o select para candidatura genérica
      if (vagaSelect) {
        vagaSelect.value = '';
      }
      
      // Mostrar o formulário
      if (formSection) {
        formSection.classList.remove('hidden');
        // Scroll suave para o formulário
        formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  initZoomSections();
});

function initZoomSections() {
  const zoomSections = document.querySelectorAll('section');

  if (!zoomSections.length) return;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) return;

  zoomSections.forEach((section) => {
    section.classList.add('zoom-section');

    const rect = section.getBoundingClientRect();
    if (rect.top > window.innerHeight * 0.9) {
      section.classList.add('zoom-pre');
    }
  });

  const zoomObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      const section = entry.target;

      if (entry.isIntersecting) {
        section.classList.remove('zoom-pre');
        section.classList.remove('zoom-out');
      } else if (entry.boundingClientRect.top < 0) {
        section.classList.add('zoom-out');
        section.classList.remove('zoom-pre');
      } else {
        section.classList.add('zoom-pre');
        section.classList.remove('zoom-out');
      }
    });
  }, {
    threshold: [0, 0.15, 0.5],
    rootMargin: '0px 0px -8% 0px'
  });

  zoomSections.forEach((section) => zoomObserver.observe(section));
}

function loadJobs() {
  // Carregar vagas da API
  console.log('Iniciando carregamento de vagas...');
  
  fetch('api_vagas.php')
    .then(response => {
      console.log('Resposta recebida:', response.status);
      if (!response.ok) {
        throw new Error(`HTTP Error: ${response.status}`);
      }
      return response.json();
    })
    .then(jobs => {
      console.log('Vagas carregadas:', jobs);
      if (jobs.length === 0) {
        console.warn('Nenhuma vaga retornada pela API');
      }
      renderJobs(jobs);
      populateJobSelect(jobs);
    })
    .catch(error => {
      console.error('Erro ao carregar vagas:', error);
      console.error('Stack:', error.stack);
    });
}

function renderJobs(jobs) {
  const carousel = document.getElementById('jobs-carousel');
  
  console.log('Renderizando vagas. Carousel encontrado:', !!carousel);
  
  if (!carousel) return;

  // Limpar o container
  carousel.innerHTML = '';

  // Se não há vagas
  if (jobs.length === 0) {
    carousel.innerHTML = '<p style="text-align: center; grid-column: 1/-1;">Nenhuma vaga disponível no momento.</p>';
    return;
  }

  // Renderizar cada vaga como slide do carrosel
  jobs.forEach((job, index) => {
    const jobSlide = document.createElement('div');
    jobSlide.className = 'carousel-slide' + (index === 0 ? ' active' : '');
    jobSlide.innerHTML = `
      <div class="job-card-carousel">
        <h3>${escapeHtml(job.title)}</h3>
        <span class="job-tag">${escapeHtml(job.type)}</span>
        <div class="job-description-block">${escapeHtml(job.description)}</div>
        <button type="button" class="btn btn-apply" data-job-id="${job.id}">Candidatar-se</button>
      </div>
    `;
    
    carousel.appendChild(jobSlide);
  });

  console.log('Slides criados:', jobs.length);

  // Criar indicadores
  createCarouselIndicators(jobs.length);

  // Adicionar event listeners aos botões de candidatar-se
  setupApplyButtons();
  
  // Configurar navegação do carrosel
  setupCarouselNavigation(jobs.length);
}

function createCarouselIndicators(total) {
  const indicators = document.getElementById('carousel-indicators');
  
  if (!indicators) return;

  indicators.innerHTML = '';
  
  for (let i = 0; i < total; i++) {
    const dot = document.createElement('span');
    dot.className = 'indicator' + (i === 0 ? ' active' : '');
    dot.addEventListener('click', () => goToSlide(i));
    indicators.appendChild(dot);
  }
}

function setupCarouselNavigation(total) {
  let currentSlide = 0;

  const prevBtn = document.getElementById('carousel-prev');
  const nextBtn = document.getElementById('carousel-next');

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      currentSlide = (currentSlide - 1 + total) % total;
      goToSlide(currentSlide);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      currentSlide = (currentSlide + 1) % total;
      goToSlide(currentSlide);
    });
  }
}

function goToSlide(index) {
  const slides = document.querySelectorAll('.carousel-slide');
  const indicators = document.querySelectorAll('.indicator');

  slides.forEach(slide => slide.classList.remove('active'));
  indicators.forEach(indicator => indicator.classList.remove('active'));

  if (slides[index]) {
    slides[index].classList.add('active');
  }
  
  if (indicators[index]) {
    indicators[index].classList.add('active');
  }
}

function populateJobSelect(jobs) {
  const vagaSelect = document.getElementById('vaga');
  
  if (!vagaSelect) return;

  // Limpar opções existentes (mantendo a primeira)
  while (vagaSelect.options.length > 1) {
    vagaSelect.remove(1);
  }

  // Adicionar opções das vagas
  jobs.forEach(job => {
    const option = document.createElement('option');
    option.value = job.id;
    option.textContent = job.title;
    vagaSelect.appendChild(option);
  });
}

function setupApplyButtons() {
  const applyButtons = document.querySelectorAll('.btn-apply');
  const formSection = document.getElementById('formulario');
  const vagaSelect = document.getElementById('vaga');

  applyButtons.forEach(button => {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      const jobId = this.getAttribute('data-job-id');
      
      // Preencher o select da vaga
      if (vagaSelect) {
        vagaSelect.value = jobId;
      }
      
      // Mostrar o formulário
      if (formSection) {
        formSection.classList.remove('hidden');
        // Scroll suave para o formulário
        formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
}

// Funcionalidade do formulário de candidatura
document.addEventListener('DOMContentLoaded', function () {
  const contactForm = document.querySelector('.contact-form');
  
  if (contactForm) {
    contactForm.addEventListener('submit', function (event) {
      event.preventDefault();
      
      const formData = new FormData(contactForm);
      console.log('Formulário enviado. Dados:', Array.from(formData.entries()));
      const jobId = formData.get('job_id');
      const resumeFile = formData.get('formulario');
      
      // Validar se uma vaga foi selecionada
      if (!jobId) {
        alert('Por favor, selecione uma vaga.');
        return;
      }

      if (!resumeFile || resumeFile.size === 0) {
        alert('Anexe seu currículo em formato PDF ou DOCX.');
        return;
      }

      const fileName = resumeFile.name.toLowerCase();
      if (!fileName.endsWith('.pdf') && !fileName.endsWith('.docx')) {
        alert('Formato inválido. Envie somente arquivos .pdf ou .docx.');
        return;
      }
      
      // Enviar candidatura via API
      fetch('api_candidatura.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        console.log('Resposta da API:', response.status, response.statusText);
        
        // Tentar fazer parse do JSON
        return response.json().then(data => {
          // Retornar tanto os dados quanto o status da resposta
          return { status: response.status, ok: response.ok, data: data };
        }).catch(error => {
          // Se falhar ao fazer parse do JSON
          console.error('Erro ao fazer parse do JSON:', error);
          return { 
            status: response.status, 
            ok: response.ok, 
            data: { error: 'Resposta inválida do servidor' } 
          };
        });
      })
      .then(result => {
        console.log('Resultado processado:', result);
        const { status, ok, data } = result;
        
        // Verificar se a requisição foi bem-sucedida
        if (ok && data.success) {
          alert('Candidatura enviada com sucesso! Agradecemos sua participação.');
          contactForm.reset();
          document.getElementById('formulario').classList.add('hidden');
        } else {
          // Mostrar erro específico
          const errorMsg = data.error || data.message || `Erro do servidor (HTTP ${status})`;
          alert('Erro: ' + errorMsg);
          console.error('Erro na resposta:', data);
        }
      })
      .catch(error => {
        console.error('Erro na requisição:', error);
        console.error('Stack:', error.stack);
        alert('Erro ao enviar candidatura. Verifique sua conexão e tente novamente.');
      });
    });
  }
});

function initThemeToggle() {
  const toggle = document.querySelector('.theme-toggle');
  if (!toggle) return;

  const body = document.body;
  const savedTheme = localStorage.getItem('labware-theme');
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const isDark = savedTheme ? savedTheme === 'dark' : prefersDark;

  applyTheme(isDark);

  toggle.addEventListener('click', () => {
    const nextIsDark = !body.classList.contains('dark-mode');
    applyTheme(nextIsDark);
  });

  function applyTheme(darkMode) {
    body.classList.toggle('dark-mode', darkMode);
    toggle.classList.toggle('active', darkMode);
    toggle.setAttribute('aria-pressed', String(darkMode));

    const icon = toggle.querySelector('i');
    const label = toggle.querySelector('span');

    if (icon) {
      icon.classList.toggle('fa-moon', !darkMode);
      icon.classList.toggle('fa-sun', darkMode);
    }

    if (label) {
      label.textContent = darkMode ? 'Light' : 'Dark';
    }

    localStorage.setItem('labware-theme', darkMode ? 'dark' : 'light');
  }
}
