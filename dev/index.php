<!DOCTYPE html>
    <html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Gestão de Comandas - Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="login">
    <div class="container-login">
        <div class="logo">
            <img src="assets/logo.png" alt="Logo do SaaS Gestão de Comandas">
        </div>

        <h1>Bem-vindo de volta!</h1>
        <p>Entre com sua conta para continuar</p>

        <form id="form-login" onsubmit="event.preventDefault();">

            <div class="field">
                <label for="email">E-mail</label>
                <input type="text" id="email" name="email" required placeholder="seunome@exemplo.com">
            </div>

            <div class="field">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required placeholder="Digite sua senha...">
            </div>

            <div class="row">
                <label><input type="checkbox" id="lembrar" /> Lembrar de mim</label>
            </div>

            <div id="mensagem-alerta" class="alerta-oculto"></div>

            <button type="submit" class="btn-entrar" id="btn-entrar">Entrar</button>

        </form>
    </div>

    <script src="js/script.js" defer></script>
</body>

</html>