(function () {
  const state = EWC.state || {};
  const buttons = document.querySelectorAll('[data-action]');
  const logNode = document.querySelector('[data-log]');
  const progressBar = document.querySelector('[data-progress-bar]');
  const progressLabel = document.querySelector('[data-progress-label]');
  let running = false;

  function updateStats(nextState) {
    ['total', 'processed', 'converted', 'skipped', 'failed'].forEach((key) => {
      const node = document.querySelector(`[data-stat="${key}"]`);
      if (node) {
        node.textContent = String(nextState[key] || 0);
      }
    });

    if (progressBar) {
      progressBar.style.width = `${nextState.progress_percent || 0}%`;
    }

    if (progressLabel) {
      progressLabel.textContent = nextState.progress_label || '';
    }

    if (logNode) {
      logNode.textContent = (nextState.log || []).join('\n');
      logNode.scrollTop = logNode.scrollHeight;
    }

    Object.assign(state, nextState);
  }

  async function request(action) {
    const body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', EWC.nonce);

    const response = await fetch(EWC.ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body: body.toString(),
      credentials: 'same-origin',
    });

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.data && result.data.message ? result.data.message : 'Request failed');
    }

    updateStats(result.data);
    return result.data;
  }

  async function runConversion() {
    if (running) {
      return;
    }

    running = true;
    toggleButtons(true);

    try {
      if (!state.total) {
        await request('ewc_scan');
      }

      while (!state.completed) {
        await request('ewc_convert_batch');
      }
    } catch (error) {
      window.alert(error.message);
    } finally {
      running = false;
      toggleButtons(false);
    }
  }

  function toggleButtons(disabled) {
    buttons.forEach((button) => {
      button.disabled = disabled;
    });
  }

  buttons.forEach((button) => {
    button.addEventListener('click', async () => {
      const action = button.getAttribute('data-action');

      if (action === 'scan') {
        try {
          toggleButtons(true);
          await request('ewc_scan');
        } catch (error) {
          window.alert(error.message);
        } finally {
          toggleButtons(false);
        }
        return;
      }

      if (action === 'reset') {
        try {
          toggleButtons(true);
          await request('ewc_reset');
        } catch (error) {
          window.alert(error.message);
        } finally {
          toggleButtons(false);
        }
        return;
      }

      if (action === 'convert') {
        await runConversion();
      }
    });
  });

  updateStats(state);
})();
