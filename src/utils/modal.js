import '../assets/css/modal.css';

export function showModal(message, type = 'alert', title = '提示', buttonOrInitialValue = '确定', cancelText = '取消') {
  return new Promise(resolve => {
    let modalOverlay = document.getElementById('custom-modal-overlay');
    if (!modalOverlay) {
      modalOverlay = document.createElement('div');
      modalOverlay.id = 'custom-modal-overlay';
      modalOverlay.className = 'custom-modal-overlay';
      modalOverlay.innerHTML = `
        <div class="custom-modal-content">
          <h3 id="custom-modal-title"></h3>
          <p id="custom-modal-message"></p>
          <textarea id="custom-modal-input" class="custom-modal-input hide"></textarea>
          <div class="custom-modal-actions">
            <button id="custom-modal-confirm-btn"></button>
            <button id="custom-modal-cancel-btn" class="hide"></button>
          </div>
        </div>
      `;
      document.body.appendChild(modalOverlay);
    }

    const modalTitleElement = document.getElementById('custom-modal-title');
    const modalMessageElement = document.getElementById('custom-modal-message');
    const modalInputElement = document.getElementById('custom-modal-input');
    const confirmBtn = document.getElementById('custom-modal-confirm-btn');
    const cancelBtn = document.getElementById('custom-modal-cancel-btn');

    modalTitleElement.textContent = title;
    
    if (type === 'prompt') {
      modalMessageElement.classList.add('hide');
      modalInputElement.classList.remove('hide');
      modalInputElement.value = buttonOrInitialValue || '';
      modalInputElement.placeholder = '请输入内容...';
      confirmBtn.textContent = '确认';
      
      setTimeout(() => {
        modalInputElement.focus();
        modalInputElement.select();
      }, 50);
    } else {
      modalMessageElement.classList.remove('hide');
      // 使用 textContent + CSS white-space:pre-line，\n 与接口里的换行才能正确显示；并兼容字面量 "\\n"
      const raw = message == null ? '' : String(message);
      const normalized = raw.replace(/\r\n/g, '\n').replace(/\\n/g, '\n');
      modalMessageElement.textContent = normalized;
      modalInputElement.classList.add('hide');
      confirmBtn.textContent = buttonOrInitialValue;
    }

    let isResolved = false;

    const confirmHandler = (event) => {
      event.stopPropagation();
      modalOverlay.classList.remove('show');
      if (isResolved) return;
      isResolved = true;
      if (type === 'prompt') {
        resolve(modalInputElement.value);
      } else {
        resolve(true);
      }
    };

    const cancelHandler = (event) => {
      event.stopPropagation();
      modalOverlay.classList.remove('show');
      if (isResolved) return;
      isResolved = true;
      if (type === 'prompt') {
        resolve(null);
      } else {
        resolve(false);
      }
    };

    const newConfirmBtn = confirmBtn.cloneNode(true);
    const newCancelBtn = cancelBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

    newConfirmBtn.addEventListener('click', confirmHandler);

    if (type === 'confirm' || type === 'prompt') {
      newCancelBtn.textContent = cancelText;
      newCancelBtn.classList.remove('hide');
      newCancelBtn.addEventListener('click', cancelHandler);
    } else {
      newCancelBtn.classList.add('hide');
    }

    setTimeout(() => {
      modalOverlay.classList.add('show');
    }, 10);
  });
}

export const customAlert = (message, title = '提示') => showModal(message, 'alert', title, '确定');
export const customConfirm = (message, title = '确认') => showModal(message, 'confirm', title, '确定', '取消');
export const customPrompt = (message, initialValue = '', title = '输入') => showModal(message, 'prompt', title, initialValue, '取消');
