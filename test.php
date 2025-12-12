<?php
/**
 * TEST.PHP - Archivo de Prueba
 *
 * Este archivo existe porque alguien quería probar Git
 * y yo soy solo un humilde archivo PHP tratando de sobrevivir
 * en un mundo de commits y merges.
 */

// Prevenir acceso directo (porque la seguridad es importante, amigos)
defined('APP_ACCESS') or define('APP_ACCESS', true);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test PHP - Mensaje Gracioso</title>
    <style>
        body {
            font-family: 'Comic Sans MS', cursive, sans-serif;
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }
        .container {
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        h1 { font-size: 3em; margin: 0; }
        .joke { font-size: 1.5em; margin: 20px 0; line-height: 1.6; }
        .emoji { font-size: 5em; animation: bounce 2s infinite; }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .footer {
            margin-top: 30px;
            font-size: 0.9em;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="emoji">🎓💻</div>
        <h1>¡Hola desde test.php!</h1>

        <div class="joke">
            <p><strong>Chiste del día:</strong></p>
            <p>¿Por qué los programadores prefieren el modo oscuro?</p>
            <p><em>¡Porque la luz atrae a los bugs! 🐛</em></p>
        </div>

        <div class="joke">
            <p><strong>Dato curioso PHP:</strong></p>
            <p>PHP significa "PHP: Hypertext Preprocessor"</p>
            <p>Sí, es un acrónimo recursivo... ¡como inception pero para nerds! 🤯</p>
        </div>

        <div class="joke">
            <p><strong>Estado del servidor:</strong></p>
            <p><?php echo "✅ PHP " . phpversion() . " funcionando perfectamente"; ?></p>
            <p><?php echo "📅 Fecha: " . date('d/m/Y H:i:s'); ?></p>
        </div>

        <div class="footer">
            <p>Este archivo fue creado con amor (y un poco de humor) 💜</p>
            <p><small>Plataforma Educativa - Testing in Progress</small></p>
        </div>
    </div>
</body>
</html>
