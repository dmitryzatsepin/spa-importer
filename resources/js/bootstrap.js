import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Подавление консольных логов в production окружении
if (import.meta && import.meta.env && import.meta.env.PROD) {
    const noop = () => { };
    // Сохраняем error, если нужно — можно отправлять на внешний логер
    console.log = noop;
    console.debug = noop;
    console.info = noop;
    console.warn = noop;
    // console.error оставляем, чтобы не терять критические ошибки UI
}

