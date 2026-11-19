document.addEventListener('DOMContentLoaded', () => {
    // Menú móvil responsivo
    const btnMenu = document.getElementById('btnMenu');
    const navLinks = document.getElementById('navLinks');
    if (btnMenu && navLinks) {
        btnMenu.addEventListener('click', () => {
            navLinks.classList.toggle('abierto');
        });
    }

    // Buscador en tiempo real para tablas
    document.querySelectorAll('[data-filtro-tabla]').forEach(input => {
        input.addEventListener('keyup', () => {
            const tablaId = input.getAttribute('data-filtro-tabla');
            const tabla = document.getElementById(tablaId);
            if (!tabla) return;
            const texto = input.value.toLowerCase();
            tabla.querySelectorAll('tbody tr').forEach(fila => {
                fila.style.display = fila.textContent.toLowerCase().includes(texto) ? '' : 'none';
            });
        });
    });

    // Simulación visual de envío en etapa Frontend (Avance 2)
    document.querySelectorAll('form[data-demo-form]').forEach(form => {
        form.addEventListener('submit', e => {
            e.preventDefault();
            const modulo = form.getAttribute('data-demo-form');
            alert(`[Vista previa Frontend] Formulario "${modulo}" validado correctamente. Listo para conectar con PHP 8 y MariaDB.`);
        });
    });
});