<?php
namespace KrothiumPHP\View;

use Exception;

class Render {
    private static string $viewPath;
    private static string $modulePath;
    private static bool $configured = false;

    private static array $folderCache = [];

    /**
     * Inicializa as configurações globais do motor de renderização, definindo os caminhos base para visões e módulos.
     *
     * Este método deve ser chamado obrigatoriamente antes de qualquer tentativa de renderização de telas. 
     * Ele valida se os diretórios informados existem no servidor e normaliza as strings de caminho, 
     * removendo barras sobressalentes ao final.
     *
     * ---
     * ## Fluxo de Configuração
     * 1. **Validação de Diretórios:** Utiliza `is_dir` para garantir que tanto o caminho das views globais quanto o dos módulos sejam acessíveis e válidos.
     * 2. **Normalização:** Aplica `rtrim` para garantir que os caminhos armazenados não terminem com `/`, evitando erros de concatenação futura (ex: `path//file.php`).
     * 3. **Ativação:** Define a flag estática `$configured` como `true`, liberando o uso dos demais métodos da classe.
     *
     * @param string $viewPath O caminho absoluto para o diretório principal de visualizações (views) da aplicação.
     * @param string $modulePath O caminho absoluto para o diretório que contém os módulos do sistema.
     * @return void
     * @throws Exception Lança uma exceção se qualquer um dos caminhos fornecidos não for um diretório válido no sistema de arquivos.
     */
    public static function configure(string $viewPath, string $modulePath): void {
        if (!is_dir(filename: $viewPath)) {
            throw new Exception(message: "View path inválido: {$viewPath}");
        }
        if (!is_dir(filename: $modulePath)) {
            throw new Exception(message: "Module path inválido: {$modulePath}");
        }
        self::$viewPath   = rtrim(string: $viewPath, characters: '/');
        self::$modulePath = rtrim(string: $modulePath, characters: '/');
        self::$configured = true;
    }

    /**
     * Verifica se a classe de renderização foi devidamente inicializada antes de sua utilização.
     *
     * Este método atua como um **guardião (guard clause)**, validando se as configurações globais 
     * necessárias (como caminhos de views e módulos) foram definidas através do método `configure()`.
     * Caso a classe tente ser utilizada sem essa preparação prévia, a execução é interrompida para 
     * evitar erros de caminhos nulos ou indefinidos.
     *
     * @return void
     * @throws Exception Lança uma exceção de configuração caso a propriedade estática `$configured` seja falsa.
     */
    private static function ensureConfigured(): void {
        if (!self::$configured) {
            throw new Exception(message: 'Render não configurado. Chame Render::configure().');
        }
    }

    /**
     * Localiza o nome real de uma pasta no sistema de arquivos de forma **case-insensitive** (insensível a maiúsculas e minúsculas) e armazena o resultado em cache.
     *
     * Este método é útil em sistemas operacionais Case-Sensitive (como o Linux), permitindo que o sistema encontre 
     * diretórios mesmo que a capitalização informada no código seja diferente da real no disco.
     * * ---
     * ## Mecanismo de Funcionamento
     * 1. **Cache Memory:** Antes de escanear o disco, verifica se o caminho já foi resolvido em `self::$folderCache` para otimizar a performance.
     * 2. **Escaneamento:** Utiliza `scandir` para listar os itens do diretório base.
     * 3. **Comparação:** Utiliza `strcasecmp` para comparar o nome de cada entrada com o alvo (`$target`). Se houver uma correspondência e a entrada for um diretório válido, o nome real é retornado.
     * 4. **Persistência de Cache:** O resultado (o nome real encontrado ou `null`) é salvo no cache estático antes do retorno.
     *
     * @param string $basePath O caminho absoluto do diretório pai onde a busca será realizada.
     * @param string $target O nome da pasta que se deseja encontrar (ex: 'views', 'Views' ou 'VIEWS').
     * @return string|null Retorna o nome exato da pasta como está gravada no disco ou `null` caso não seja encontrada.
     */
    private static function getRealFolderName(string $basePath, string $target): ?string {
        $cacheKey = $basePath . '|' . strtolower(string: $target);
        if (isset(self::$folderCache[$cacheKey])) {
            return self::$folderCache[$cacheKey];
        }
        foreach (scandir(directory: $basePath) as $entry) {
            if (strcasecmp(string1: $entry, string2: $target) === 0 && is_dir(filename: $basePath . '/' . $entry)) {
                return self::$folderCache[$cacheKey] = $entry;
            }
        }
        return self::$folderCache[$cacheKey] = null;
    }

    /**
     * Resolve o caminho físico completo de um arquivo de **View** (Visão) no sistema de arquivos.
     * * O método identifica a localização correta do arquivo `.php`, tratando de forma distinta se a view 
     * pertence ao diretório global da aplicação ou se está contida dentro de um **Módulo** específico.
     * * ---
     * ## Lógica de Resolução de Caminhos
     * 1. **Global:** Se `$module` for nulo, o caminho é construído usando o diretório base de views definido em `self::$viewPath`.
     * 2. **Modular:** Se um módulo for especificado:
     * - Valida a existência da pasta do módulo através de `getRealFolderName`.
     * - Localiza dinamicamente a pasta de visualizações (geralmente nomeada como `Views`) dentro da estrutura do módulo.
     * - Retorna o caminho absoluto concatenando o caminho do módulo, a pasta de views encontrada e o nome do arquivo.
     * * 
     * * @param string $view O nome ou caminho relativo do arquivo de visualização (sem a extensão .php).
     * @param string|null $module O nome do módulo opcional onde a view reside.
     * @return string O caminho absoluto completo para o arquivo da view no servidor.
     * @throws Exception Lança uma exceção caso o módulo informado não exista ou se a pasta interna 'Views' não for encontrada dentro do módulo.
     */
    private static function resolveViewPath(string $view, ?string $module): string {
        self::ensureConfigured();
        if ($module) {
            $realModule = self::getRealFolderName(basePath: self::$modulePath, target: $module);
            if (!$realModule) {
                throw new Exception(message: "Módulo '{$module}' não encontrado.");
            }

            $realViews = self::getRealFolderName(
                basePath: self::$modulePath . "/{$realModule}",
                target: 'Views'
            );
            if (!$realViews) {
                throw new Exception(message: "Pasta 'Views' do módulo '{$module}' não encontrada.");
            }
            return self::$modulePath . "/{$realModule}/{$realViews}/{$view}.php";
        }
        return self::$viewPath . "/{$view}.php";
    }

    /**
     * Renderiza uma visualização (view) do sistema, permitindo a extração de dados e o uso opcional de layouts.
     *
     * O método gerencia o fluxo de exibição da interface, suportando tanto views simples quanto views encapsuladas 
     * em layouts (Master Pages). Ele utiliza o controle de buffer de saída do PHP para capturar o conteúdo da view 
     * antes de injetá-lo no layout escolhido.
     *
     * ---
     * ## Fluxo de Renderização
     * 1. **Resolução de Caminho:** Determina o caminho físico do arquivo da view através do método `resolveViewPath`, considerando se a view pertence a um módulo específico.
     * 2. **Extração de Dados:** Utiliza a função `extract` com a flag `EXTR_SKIP` para transformar chaves de um array em variáveis acessíveis dentro do arquivo PHP da view, sem sobrescrever variáveis locais existentes.
     * 3. **Captura de Conteúdo:** Inicia um buffer de saída (`ob_start`), inclui o arquivo da view e armazena o resultado na variável `$content`.
     * 4. **Tratamento de Layout:** * - Se nenhum layout for informado, imprime o conteúdo da view diretamente.
     * - Se um layout for informado, resolve o caminho do layout e o inclui. O arquivo de layout deve conter uma instrução para imprimir a variável `$content`.
     * 
     *
     * @param string $view O nome ou caminho relativo da view a ser renderizada (ex: 'usuarios/perfil').
     * @param array $data Uma matriz (array) associativa de dados que serão convertidos em variáveis para uso dentro da view.
     * @param string|null $layout O nome opcional do layout (template mestre) que deve encapsular a view.
     * @param string|null $module O nome opcional do módulo onde a view está localizada, caso não esteja no diretório padrão.
     * @return void O método imprime o conteúdo diretamente na tela e não retorna valor.
     * @throws Exception Lança uma exceção caso o arquivo da view ou o arquivo do layout não sejam encontrados no sistema de arquivos.
     */
    public static function render(string $view, array $data = [], ?string $layout = null, ?string $module = null): void {
        $viewPath = self::resolveViewPath(view: $view, module: $module);
        if (!file_exists(filename: $viewPath)) {
            throw new Exception(message: "View '{$view}' não encontrada.");
        }
        if ($data) {
            extract($data, EXTR_SKIP);
        }
        ob_start();
        require $viewPath;
        $content = ob_get_clean();
        if (!$layout) {
            echo $content;
            return;
        }
        $layoutPath = self::resolveViewPath(view: $layout, module: $module);
        if (!file_exists(filename: $layoutPath)) {
            throw new Exception(message: "Layout '{$layout}' não encontrado.");
        }
        require $layoutPath;
    }
}