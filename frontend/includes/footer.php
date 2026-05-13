<?php
/**
 * footer.php - Pie de página Premium SIRA
 * [V14.6] Parche de Robustez para Login y vistas anónimas.
 */

// 1. Inicialización de Seguridad (Evita los Warnings vistos en el Login)
if (!isset($config_social)) {
    $config_social = [
        "twitter" => "", "instagram" => "", "facebook" => "", 
        "whatsapp" => "", "email_soporte" => "sira@sira.es"
    ];
}
$es_admin = $es_admin ?? (isset($_SESSION['user_rol']) && in_array($_SESSION['user_rol'], ['admin', 'root']));
$es_root  = $es_root ?? (isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'root');
$modo_edicion_social = $modo_edicion_social ?? false;
?>
<footer class="sira-footer">
    <div class="footer-container">
        <!-- 1. Bloque de Contenido Principal -->
        <div class="sira-footer-main">
            <!-- 1. Identidad -->
            <div class="footer-brand">
                <img src="<?= $base_url ?>/assets/img/logo-full.svg" alt="SIRA Logo" class="footer-logo">
                <p class="footer-description">
                    <strong>SIRA Project</strong> — Sistema Integral de Riego Automático. 
                    Monitorización y gestión dinámica de infraestructura agrícola mediante IoT.
                </p>
            </div>

            <!-- 2. Estado (Simulado para TFG) -->
            <div class="footer-status-box">
                <span class="status-header">Estado de Conectividad</span>
                <div class="status-item">
                    <div class="pulse-dot"></div>
                    <span>SIRA API: Operativa</span>
                </div>
                <div class="status-item">
                    <div class="pulse-dot" style="background: #34d399;"></div>
                    <span>Nodos IoT: Sincronizados</span>
                </div>
                <div class="status-item">
                    <div class="pulse-dot" style="background: #fbbf24; animation-delay: 1s;"></div>
                    <span>BBDD: Latencia Optimizada</span>
                </div>
            </div>

            <!-- 3. Enlaces Rápidos -->
            <div class="footer-links-group">
                <h4>Recursos del Proyecto</h4>
                <a href="#" class="footer-link">Documentación Técnica</a>
                <a href="#" class="footer-link">Guía de Usuario</a>
                <a href="#" class="footer-link">API Reference</a>
                <a href="#" class="footer-link">Configuración IoT</a>
            </div>

            <!-- 4. Comunidad y Contacto -->
            <div class="footer-social-group">
                <div class="social-header">
                    <h4>Conecta con SIRA</h4>
                    <?php if ($es_root): ?>
                        <a href="?edit_social=1" class="admin-gear-btn" title="Configurar Redes Sociales (Solo Root)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="social-icons">
                    <?php if ($es_root || !empty($config_social['twitter'])): ?>
                        <a href="<?= htmlspecialchars($config_social['twitter'] ?: '#') ?>" class="social-icon" target="_blank" title="X">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($es_root || !empty($config_social['instagram'])): ?>
                        <a href="<?= htmlspecialchars($config_social['instagram'] ?: '#') ?>" class="social-icon" target="_blank" title="Instagram">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                        </a>
                    <?php endif; ?>

                    <?php if ($es_root || !empty($config_social['facebook'])): ?>
                        <a href="<?= htmlspecialchars($config_social['facebook'] ?: '#') ?>" class="social-icon" target="_blank" title="Facebook">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                        </a>
                    <?php endif; ?>

                    <?php if ($es_root || !empty($config_social['whatsapp'])): ?>
                        <a href="<?= htmlspecialchars($config_social['whatsapp'] ?: '#') ?>" class="social-icon" target="_blank" title="WhatsApp">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 1 1-7.6-14 8.38 8.38 0 0 1 3.8.9L21 3z"></path></svg>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="contact-email">
                    <a href="mailto:<?= htmlspecialchars($config_social['email_soporte'] ?: 'sira@sira.es') ?>" class="email-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                        <span><?= htmlspecialchars($config_social['email_soporte'] ?: 'sira@sira.es') ?></span>
                    </a>
                </div>
            </div>
        </div>

        <?php if ($modo_edicion_social): ?>
            <!-- Modal de Configuración Social (Zero-JS) -->
            <div class="confirm-overlay">
                <div class="confirm-card highlight-glow" style="max-width: 500px; text-align: left;">
                    <div class="confirm-header" style="justify-content: flex-start; gap: 10px;">
                        <span style="font-size: 1.5rem;">⚙️</span>
                        <h2 style="margin: 0;">Configurar Enlaces Rápidos</h2>
                    </div>
                    <form action="dashboard.php" method="POST" style="margin-top: 1.5rem;">
                        <input type="hidden" name="accion" value="update_social_links">
                        
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label>X (Twitter)</label>
                            <input type="text" name="twitter" value="<?= htmlspecialchars($config_social['twitter']) ?>" placeholder="https://x.com/tu_cuenta">
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label>Instagram</label>
                            <input type="text" name="instagram" value="<?= htmlspecialchars($config_social['instagram']) ?>" placeholder="https://instagram.com/tu_cuenta">
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label>Facebook</label>
                            <input type="text" name="facebook" value="<?= htmlspecialchars($config_social['facebook']) ?>" placeholder="https://facebook.com/tu_cuenta">
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label>WhatsApp</label>
                            <input type="text" name="whatsapp" value="<?= htmlspecialchars($config_social['whatsapp']) ?>" placeholder="https://wa.me/34000000000">
                        </div>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label>Email de Soporte</label>
                            <input type="email" name="email_soporte" value="<?= htmlspecialchars($config_social['email_soporte']) ?>" placeholder="sira@sira.es">
                        </div>

                        <div class="confirm-actions">
                            <button type="submit" class="btn-sira btn-primary">Guardar Cambios</button>
                            <a href="dashboard.php" class="confirm-btn-no">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2. Barra de Metadatos Inferior -->
        <div class="sira-footer-meta">
            <p>© <?= date('Y') ?> SIRA Project — Diseñado para una agricultura eficiente y sostenible.</p>
            <p>
                Versión <span class="version-tag">v1.0.1</span> 
                | Estado: <span style="font-family: monospace; opacity: 0.7;">Estable (TFG Final)</span>
            </p>
        </div>
    </div>
</footer>

</body>
</html>
