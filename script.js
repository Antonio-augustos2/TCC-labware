console.log('script.js carregado!');

document.addEventListener('DOMContentLoaded', function () {
  console.log('DOM pronto!');
  
  // Funcionalidade de upload de arquivo
  const fileInput = document.getElementById('anexoDocumento');
  const fileButton = document.querySelector('.file-upload-button');

  if (fileInput && fileButton) {
    fileButton.addEventListener('click', function (event) {
      event.preventDefault();
      fileInput.click();
    });
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
});

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
        <p class="job-description">${escapeHtml(job.description)}</p>
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

// Funcionalidade do formulário de candidatura
document.addEventListener('DOMContentLoaded', function () {
  const contactForm = document.querySelector('.contact-form');
  
  if (contactForm) {
    contactForm.addEventListener('submit', function (event) {
      event.preventDefault();
      
      const formData = new FormData(contactForm);
      const jobId = formData.get('job_id');
      
      // Validar se uma vaga foi selecionada
      if (!jobId) {
        alert('Por favor, selecione uma vaga.');
        return;
      }
      
      // Enviar candidatura via API
      fetch('api_candidatura.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Candidatura enviada com sucesso! Agradecemos sua participação.');
          contactForm.reset();
          document.getElementById('formulario').classList.add('hidden');
        } else {
          alert('Erro: ' + (data.error || 'Falha ao enviar candidatura'));
        }
      })
      .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao enviar candidatura. Tente novamente.');
      });
    });
  }
});
