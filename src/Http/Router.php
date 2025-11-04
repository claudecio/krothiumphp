<?php
namespace KrothiumPHP\Http;

use Exception;

class Router {
    private static $routes = [];
    private static $params = [];    
    private static $ROUTER_MODE = null;
    private static $APP_SYS_MODE = null;
    private static string $basePath = '';
    private static $currentGroupPrefix = '';
    private static $currentGroupMiddlewares = [];
    private static $ROUTER_ALLOWED_ORIGINS = ['*'];
    private static array $requiredConstants = ['ROUTER_MODE', 'APP_SYS_MODE'];
    private static array $allowedHttpRequests = ['GET','POST','PUT','PATCH','DELETE','OPTIONS'];

    /**
     * Inicializa o roteador e define as configurações essenciais, como caminhos base, modos de operação e permissões de CORS.
     *
     * Este método estático é o ponto de partida para configurar o roteador (`Router`) e garantir que todas as variáveis de ambiente necessárias estejam prontas para processar requisições.
     *
     * #### Fluxo de Operação:
     * 1.  **Verificação de Constantes:** Chama `self::checkRequiredConstants()` para garantir que todas as constantes obrigatórias do sistema estejam definidas. (Se falhar, a execução é encerrada).
     * 2.  **Definição do Caminho Base (`$basePath`):** Verifica se a constante `ROUTER_BASE_PATH` está definida. Se estiver, define o caminho base da aplicação, garantindo que ele comece com `/`.
     * 3.  **Registro na Sessão:** Armazena o caminho base (`$basePath`) na sessão (`$_SESSION['ROUTER_BASE_PATH']`).
     * 4.  **Definição de Modos:** Define as propriedades estáticas `self::$ROUTER_MODE` (modo do roteador, e.g., 'JSON', 'WEB') e `self::$APP_SYS_MODE` (modo do sistema, e.g., 'DEV', 'PROD') com seus valores em 
     *       caixa alta (uppercase).
     * 5.  **Configuração de CORS:** Se o roteador estiver no modo 'JSON' e a constante `ROUTER_ALLOWED_ORIGINS` estiver definida, define os domínios permitidos para requisições *Cross-Origin* (CORS).
     *
     * @return void
     */
    public static function init() {
        // Verifica as constantes obrigatórias
        self::checkRequiredConstants();

        // Verifica se tem diretório base definido
        self::$basePath = defined(constant_name: 'ROUTER_BASE_PATH') 
            ? '/' . trim(string: ROUTER_BASE_PATH, characters: '/')
            : '';

        $_SESSION['ROUTER_BASE_PATH'] = self::$basePath;

        // Define os modos do roteador e do sistema
        self::$ROUTER_MODE = strtoupper(string: ROUTER_MODE);
        self::$APP_SYS_MODE = strtoupper(string: APP_SYS_MODE);

        // Define os domínios permitidos para CORS
        if (self::$ROUTER_MODE === 'JSON' && defined(constant_name: 'ROUTER_ALLOWED_ORIGINS')) {
            self::$ROUTER_ALLOWED_ORIGINS = ROUTER_ALLOWED_ORIGINS;
        }
    }

    /**
     * Retorna o modo de operação atual do roteador.
     *
     * Este método estático é utilizado para determinar o contexto de execução do roteador, geralmente indicando se ele está operando em modo de navegação web ('VIEW') ou em modo de API ('JSON').
     *
     * @return string O modo de roteamento como uma string.
     */
    public static function getMode(): string {
        return self::$ROUTER_MODE;
    }

    /**
     * Envia uma resposta JSON padronizada de erro e encerra a execução do script.
     *
     * Este método estático é um utilitário para endpoints de API, usado para comunicar falhas de forma consistente. Ele define o código de status HTTP do erro e envia uma mensagem detalhada no corpo da resposta JSON.
     *
     * #### Fluxo de Operação:
     * 1.  **Define o Código HTTP:** O código de status HTTP (`$code`) é definido usando `http_response_code()` (ex: 400 Bad Request, 401 Unauthorized, 500 Internal Server Error).
     * 2.  **Define o Cabeçalho:** O cabeçalho `Content-Type` é configurado para `application/json; charset=utf-8`.
     * 3.  **Envia o JSON:** Uma resposta JSON é construída com um status fixo de 'error' e a mensagem de erro fornecida (`$msg`).
     * 4.  **Encerra a Execução:** A execução do script é finalizada com `exit`, impedindo que códigos adicionais sejam processados após o envio da resposta.
     *
     * @param int $code O código de status HTTP a ser enviado na resposta de erro.
     * @param string $msg A mensagem de erro detalhada a ser incluída no corpo da resposta JSON.
     * @return void Este método não retorna um valor, pois ele finaliza a execução do script.
     */
    private static function jsonError(int $code, string $msg): void {
        http_response_code(response_code: $code);
        header(header: 'Content-Type: application/json; charset=utf-8');
        echo json_encode(value: ["status" => 'error', "message" => $msg]);
        exit;
    }

    /**
     * Verifica se todas as constantes de configuração essenciais estão definidas no ambiente de execução.
     *
     * Este método estático é um **verificador de saúde** (health check) usado para garantir que o ambiente da 
     * aplicação esteja corretamente configurado antes de prosseguir com a execução. Ele itera sobre uma lista 
     * pré-definida de constantes (`self::$requiredConstants`) que são consideradas cruciais para a operação do sistema.
     *
     * #### Fluxo de Operação:
     * 1.  **Iteração:** Percorre o array estático que lista os nomes das constantes obrigatórias.
     * 2.  **Verificação:** Para cada nome de constante, ele usa `defined()` para verificar se a constante existe no escopo global do PHP.
     * 3.  **Ação em Caso de Falha:** Se uma constante obrigatória **não estiver definida**, o método assume uma falha crítica de configuração. 
     *      Ele chama o método `self::jsonError()`, que envia uma resposta JSON com o código HTTP **500 Internal Server Error** e uma mensagem 
     *      detalhando qual constante está faltando, encerrando a execução do script.
     *
     * @return void Este método não retorna um valor em caso de sucesso; ele apenas garante que as constantes existam. Em caso de falha, ele envia uma resposta HTTP de erro e encerra o script.
     */
    private static function checkRequiredConstants(): void {
        foreach (self::$requiredConstants as $constant) {
            if (!defined(constant_name: $constant)) {
                self::jsonError(code: 500, msg: "Constante '{$constant}' não definida.");
            }
        }
    }

    // ===================================
    // MÉTODOS HTTP
    // ===================================
    public static function get(string $uri, array $handler, array $middlewares = []): void { self::addRoute(method: 'GET', uri: $uri, handler: $handler, middlewares: $middlewares); }
    public static function post(string $uri, array $handler, array $middlewares = []): void { self::addRoute(method: 'POST', uri: $uri, handler: $handler, middlewares: $middlewares); }
    public static function put(string $uri, array $handler, array $middlewares = []): void { self::addRoute(method: 'PUT', uri: $uri, handler: $handler, middlewares: $middlewares); }
    public static function patch(string $uri, array $handler, array $middlewares = []): void { self::addRoute(method: 'PATCH', uri: $uri, handler: $handler, middlewares: $middlewares); }
    public static function delete(string $uri, array $handler, array $middlewares = []): void { self::addRoute(method: 'DELETE', uri: $uri, handler: $handler, middlewares: $middlewares); }

    /**
     * Adiciona uma nova definição de rota à lista de rotas do roteador.
     *
     * Este método privado é o núcleo do registro de rotas. Ele constrói o caminho final da rota (URI) combinando o prefixo de grupo atual, se houver, com o URI fornecido, e armazena os detalhes da rota (controlador, ação e middlewares) em um array estático (`self::$routes`).
     *
     * @param string $method O método HTTP (e.g., 'GET', 'POST', 'PATCH').
     * @param string $uri A URI específica da rota (relativa ao prefixo do grupo, se houver).
     * @param string $handler Uma string contendo a classe do controlador e o método de ação (ex: 'Controller@method').
     * @param array $middlewares Um array opcional de middlewares específicos desta rota.
     * @return void
     */
    private static function addRoute(string $method, string $uri, array $handler, array $middlewares = []) {
        $path = '/' . trim(string: self::$currentGroupPrefix . '/' . trim(string: $uri, characters: '/'), characters: '/');
        [$controller, $action] = $handler;
        self::$routes[$method][] = [
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action,
            'middlewares' => array_merge(self::$currentGroupMiddlewares, $middlewares)
        ];
    }

    /**
     * Agrupa um conjunto de rotas sob um prefixo de URI e aplica middlewares em comum.
     *
     * Este método estático é uma ferramenta poderosa para organizar rotas, permitindo que todas as rotas definidas dentro da função de callback (`$callback`) herdem um prefixo de URI comum e uma lista de middlewares.
     *
     * #### Fluxo de Operação:
     * 1.  **Backup de Contexto:** Os prefixos e middlewares atuais (`self::$currentGroupPrefix` e `self::$currentGroupMiddlewares`) são salvos temporariamente. Isso é essencial para suportar o aninhamento (grupos dentro de grupos).
     * 2.  **Definição do Novo Contexto:** O prefixo do novo grupo (`$prefix`) é concatenado ao prefixo existente (`$previousPrefix`), e os novos middlewares são mesclados com os existentes.
     * 3.  **Execução das Rotas:** A função de callback (`$callback`) é executada. Todas as chamadas de rotas (`GET`, `POST`, etc.) feitas aqui dentro usarão o novo prefixo e herdarão os novos middlewares.
     * 4.  **Restauração do Contexto:** Após a execução do callback, os prefixos e middlewares originais são restaurados. Isso garante que rotas definidas após o grupo não sejam afetadas pelo prefixo ou middlewares internos do grupo.
     *
     * @param string $prefix O prefixo da URI a ser aplicado a todas as rotas dentro do grupo (ex: '/api/v1').
     * @param callable $callback A função que contém a definição das rotas a serem agrupadas.
     * @param array $middlewares Um array opcional de middlewares que serão aplicados a todas as rotas dentro deste grupo e em seus subgrupos.
     * @return void
     */
    public static function group(string $prefix, callable $callback, array $middlewares = []): void {
        $previousPrefix = self::$currentGroupPrefix ?? '';
        $previousMiddlewares = self::$currentGroupMiddlewares ?? [];
    
        self::$currentGroupPrefix = $previousPrefix . $prefix;
        self::$currentGroupMiddlewares = array_merge($previousMiddlewares, $middlewares);
    
        $callback();
    
        self::$currentGroupPrefix = $previousPrefix;
        self::$currentGroupMiddlewares = $previousMiddlewares;
    }

    /**
     * Verifica se o caminho da requisição (URI) corresponde ao padrão de uma rota registrada.
     *
     * Este método estático privado é essencial para o roteador. Ele compara o caminho da URI solicitada pelo 
     * cliente com o padrão de rota (`$routePath`) e extrai quaisquer parâmetros dinâmicos presentes na URI.
     *
     * @param string $routePath O padrão de URI da rota registrada (pode conter placeholders como '/users/{id}').
     * @param string $requestPath A URI real da requisição (ex: '/users/123').
     * @return bool Retorna `true` se o `$requestPath` corresponder ao `$routePath`; caso contrário, retorna `false`.
     */
    private static function matchPath($routePath, $requestPath): bool {
        self::$params = [];

        $routeParts = explode(separator: '/', string: trim(string: $routePath, characters: '/'));
        $reqParts = explode(separator: '/', string: trim(string: $requestPath, characters: '/'));

        if(count($routeParts) !== count($reqParts)) return false;

        foreach ($routeParts as $i => $part) {
            if (preg_match(pattern: '/^{\w+}$/', subject: $part)) {
                self::$params[] = $reqParts[$i];
            } elseif ($part !== $reqParts[$i]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Extrai os dados enviados no corpo da requisição HTTP para métodos específicos (PUT, DELETE, PATCH).
     *
     * Este método estático privado é crucial para APIs RESTful, pois os dados para métodos não-POST e não-GET (como PUT, PATCH e DELETE) 
     * são enviados no corpo da requisição e não são automaticamente populados nas superglobais do PHP.
     *
     * #### Fluxo de Operação:
     * 1.  **Verificação de Método:** Verifica se o método HTTP (`$method`) é um dos suportados ('PUT', 'DELETE', 'PATCH'). Se não for, retorna um array vazio.
     * 2.  **Leitura do Input:** Lê o conteúdo bruto do corpo da requisição (`php://input`). Se estiver vazio, retorna um array vazio.
     * 3.  **Processamento Condicional:**
     * * **JSON (`application/json`):** Se o `Content-Type` for JSON, o conteúdo é decodificado. Se a decodificação falhar, o método chama `self::jsonError()` para enviar uma resposta de erro HTTP 500 e encerrar a execução.
     * * **Form Data (Outros):** Caso contrário, o conteúdo é tratado como uma string de query (`application/x-www-form-urlencoded`) e analisado usando `parse_str`.
     * 4.  **Limpeza:** Remove a chave `_method` (se presente), que é frequentemente usada para simular métodos HTTP em formulários HTML.
     *
     * @param string $method O método HTTP da requisição (e.g., 'PUT', 'DELETE', 'PATCH').
     * @return array Um array associativo contendo os dados extraídos do corpo da requisição.
     * @return void Este método encerra a execução com uma resposta JSON de erro (código 500) em caso de falha na decodificação JSON.
     */
    private static function extractRequestData(string $method): array {
        if(!in_array(needle: $method, haystack: ['PUT', 'DELETE', 'PATCH'])) return [];
        $input = file_get_contents(filename: 'php://input');
        if(empty($input)) return [];

        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if(str_contains(haystack: $type, needle: 'application/json')) {
            $data = json_decode(json: $input, associative: true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                self::jsonError(code: 500, msg: 'Erro ao decodificar JSON: ' . json_last_error_msg());
            }
        } else {
            parse_str(string: $input, result: $data);
        }

        // Remove o _method se tiver
        if(isset($data['_method'])) {
            unset($data['_method']);
        }
        return $data;
    }

    /**
     * Prepara e retorna o array final de parâmetros a ser passado para o método de ação do controlador.
     *
     * Este método estático privado combina os parâmetros de rota dinâmicos extraídos do URI (`self::$params`) com quaisquer dados adicionais passados no corpo da requisição (`$params`), especificamente para métodos que enviam dados de forma não-tradicional (PUT, DELETE, PATCH).
     *
     * #### Fluxo de Operação:
     * 1.  **Verificação de Método:** Checa se o método HTTP é 'PUT', 'DELETE' ou 'PATCH'.
     * 2.  **Combinação Condicional:**
     * * **Se for PUT, DELETE ou PATCH:** Os parâmetros da rota (`self::$params`) são combinados com os dados do corpo da requisição (`$params`) usando `array_merge`.
     * * **Se for GET, POST, etc.:** Apenas os parâmetros da rota (`self::$params`) são usados.
     * 3.  **Normalização:** O array resultante é reindexado numericamente usando `array_values()` para garantir que os argumentos sejam passados corretamente para a função de ação do controlador.
     *
     * @param string $method O método HTTP da requisição (e.g., 'GET', 'POST', 'PUT').
     * @param array|null $params Um array opcional contendo dados adicionais da requisição (geralmente o corpo do payload para PUT/PATCH/DELETE).
     * @return array Um array indexado numericamente contendo a lista final de argumentos para o método do controlador.
     */
    private static function prepareMethodParameters(string $method, ?array $params = []): array {
        return array_values(array: in_array(needle: $method, haystack: ['PUT', 'DELETE', 'PATCH']) 
            ? array_merge(self::$params, $params) 
            : self::$params);
    }

    /**
     * Determina se uma rota registrada corresponde ao método HTTP e à URI da requisição atual.
     *
     * Este método estático privado é o principal mecanismo de correspondência de rotas do roteador. 
     * Ele verifica se o método HTTP da rota é o mesmo da requisição e, em seguida, usa o método auxiliar 
     * `matchPath` para verificar se o padrão da URI da rota corresponde ao caminho solicitado, considerando 
     * quaisquer parâmetros dinâmicos.
     *
     * @param string $method O método HTTP da requisição atual (e.g., 'GET', 'POST').
     * @param string $uri A URI solicitada pelo cliente (caminho da requisição).
     * @param array $route Um array de definição de rota contendo as chaves 'method' e 'path'.
     * @return bool Retorna `true` se o método HTTP e o caminho da URI corresponderem; caso contrário, retorna `false`.
     */
    private static function matchRoute(string $method, string $uri, array $route): bool {
        return $route['method'] === $method && self::matchPath(routePath: $route['path'], requestPath: $uri);
    }

    /**
     * Configura os cabeçalhos Cross-Origin Resource Sharing (CORS) para requisições de API.
     *
     * Este método privado verifica se a requisição é permitida de acordo com a política de CORS definida na aplicação.
     * Ele permite ou nega o acesso de origens externas com base nas configurações e no modo de operação do sistema.
     *
     * @param string $method O método HTTP da requisição atual (e.g., 'OPTIONS', 'GET', 'POST').
     * @return void Este método encerra a execução em caso de requisições OPTIONS ou de origem não permitida.
     */
    private static function corsSetup(string $method): void {
        if (self::$ROUTER_MODE !== 'JSON') return;

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowAll = in_array(needle: '*', haystack: self::$ROUTER_ALLOWED_ORIGINS);
        $isAllowed = in_array(needle: $origin, haystack: self::$ROUTER_ALLOWED_ORIGINS);

        if ($allowAll || $isAllowed || self::$APP_SYS_MODE === 'DEV') {
            header(header: "Access-Control-Allow-Origin: $origin");
        } else {
            self::jsonError(code: 403, msg: "Origem '{$origin}' não permitida pelo CORS.");
        }

        header(header: 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header(header: 'Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($method === 'OPTIONS') {
            http_response_code(response_code: 204);
            exit;
        }
    }

    /**
     * Executa uma lista de funções de middleware em sequência para processar a requisição.
     *
     * Este método estático é o coração do sistema de middlewares, sendo responsável por iterar sobre todos os middlewares definidos para uma rota. Ele extrai a classe, o método e os argumentos de cada middleware, executa-o e verifica o resultado para determinar se o processamento da requisição deve continuar ou ser bloqueado.
     *
     * @param array $middlewares Um array de middlewares a serem executados. Cada middleware deve ser um array no formato `[Classe::class, 'método', ...argumentos]`.
     * @return bool Retorna `true` se todos os middlewares passarem (ou seja, retornarem um valor diferente de `false` ou um array com status 'success'). Retorna `false` se qualquer middleware bloquear a requisição ou retornar um erro, ou se ocorrer um erro interno (classe/método não encontrados, formato inválido).
     *
     * @throws Exception Este método gerencia internamente as exceções, formatando a resposta para JSON e encerrando a execução.
     */
    public static function runMiddlewares(array $middlewares): bool {
        foreach ($middlewares as $middleware) {
            try {
                // valida formato
                if (!is_array(value: $middleware) || count(value: $middleware) < 2) {
                    http_response_code(response_code: 500);
                    header(header: 'Content-Type: application/json; charset=utf-8');
                    echo json_encode(value: [
                        "status" => 'error',
                        "message" => "Formato inválido do middleware. Esperado: [Classe::class, 'método', ...args]"
                    ]);
                    return false;
                }
                // Classe
                $class = $middleware[0];
                array_shift(array: $middleware);
                // Método
                $method = $middleware[0];
                array_shift(array: $middleware);
                // Argumentos
                $args = $middleware;
                // Valida se a classe existe
                if (!class_exists(class: $class)) {
                    http_response_code(response_code: 500);
                    header(header: 'Content-Type: application/json; charset=utf-8');
                    echo json_encode(value: [
                        "status" => 'error',
                        "message" => "Classe de middleware '{$class}' não encontrada."
                    ]);
                    return false;
                }
                // Valida se o método existe
                if (!method_exists(object_or_class: $class, method: $method)) {
                    http_response_code(response_code: 500);
                    header(header: 'Content-Type: application/json; charset=utf-8');
                    echo json_encode(value: [
                        "status" => 'error',
                        "message" => "Método '{$method}' não existe na classe '{$class}'."
                    ]);
                    return false;
                }
                // instancia (suporta métodos de instância)
                $instance = new $class();
                // executa middleware
                $result = call_user_func_array(callback: [$instance, $method], args: $args);
                // bloqueou a requisição
                if ($result === false) {
                    http_response_code(response_code: 403);
                    header(header: 'Content-Type: application/json; charset=utf-8');
                    echo json_encode(value: [
                        "status" => 'error',
                        "message" => "{$class}::{$method} bloqueou a requisição.",
                    ]);
                    return false;
                }
                // retorno array de erro
                if (is_array(value: $result)) {
                    $status = $result['status'] ?? 'error';
                    if ($status !== 'success') {
                        http_response_code(response_code: $result['response_code'] ?? 403);
                        header(header: 'Content-Type: application/json; charset=utf-8');
                        $response = [
                            "response_code" => $result['response_code'] ?? 403,
                            "status" => $status,
                            "message" => $result['message'] ?? 'Erro no middleware'
                        ];
                        if (isset($result['output'])) {
                            $response['output'] = $result['output'];
                        }
                        echo json_encode(value: $response);
                        return false;
                    }
                }
            } catch (Exception $e) {
                http_response_code(response_code: 500);
                header(header: 'Content-Type: application/json; charset=utf-8');
                echo json_encode(value: [
                "status" => 'error',
                    "message" => $e->getMessage()
                ]);
                return false;
            }
        }

        return true; // passou por todos os middlewares
    }

    /**
     * Lida com a situação em que nenhuma rota corresponde à URI solicitada.
     *
     * Este método privado é o manipulador padrão para o erro 404 (Not Found). O comportamento de retorno depende do modo de operação do roteador (`self::$ROUTER_MODE`).
     *
     * #### Fluxo de Operação:
     * 1.  **Modo VIEW (Navegação Web):**
     * * Verifica se a constante `ERROR_404_VIEW_PATH` está definida e se o arquivo de view correspondente realmente existe. Se a configuração estiver incorreta, envia um erro JSON `500 Internal Server Error`.
     * * Se a configuração estiver correta, define o código de resposta HTTP para `404 Not Found` e inclui o arquivo de view definido, renderizando a página de erro.
     * 2.  **Modo JSON (API):**
     * * Chama o método `self::jsonError()`, que envia uma resposta JSON padronizada com o código HTTP `404 Not Found` e a mensagem de erro.
     *
     * @return void Este método não retorna um valor; ele gerencia a saída HTTP (seja HTML ou JSON) e encerra a execução do script.
     */
    private static function pageNotFound(): void {
        if (self::$ROUTER_MODE === 'VIEW') {
            if (!defined(constant_name: 'ERROR_404_VIEW_PATH') || !file_exists(filename: ERROR_404_VIEW_PATH)) {
                self::jsonError(code: 500, msg: "Erro na configuração da página 404.");
            }
            http_response_code(response_code: 404);
            require ERROR_404_VIEW_PATH;
        } else {
            self::jsonError(code: 404, msg: 'Página não encontrada.');
        }
    }

    /**
     * Inicia o processo de roteamento, buscando a rota correspondente à requisição e executando o controlador.
     *
     * Este método estático é o ponto de entrada principal para o roteador. Ele determina o método HTTP e a URI solicitada, encontra a rota correspondente, executa os middlewares associados e, finalmente, invoca o método de ação do controlador.
     *
     * #### Fluxo de Operação Detalhado:
     * 1.  **Verificação Inicial e Coleta de Dados:**
     * - Garante que as constantes necessárias estejam definidas (`checkRequiredConstants`).
     * - Determina o método HTTP (`$method`), tratando simulações de POST (via `_method`).
     * - Extrai a URI (`$uri`) e a limpa de `basePath` e barras laterais.
     * 2.  **Validação de Método:** Verifica se o `$method` é permitido. Se não for, envia um erro **405 Method Not Allowed** e encerra.
     * 3.  **Configuração CORS:** Executa `corsSetup` para definir cabeçalhos CORS e tratar requisições `OPTIONS`.
     * 4.  **Extração de Payload:** Extrai os dados do corpo da requisição (`$requestData`) para métodos como `PUT`, `DELETE` e `PATCH`.
     * 5.  **Busca e Execução da Rota (Loop):** Itera sobre as rotas registradas para o método HTTP atual:
     * - **Match da Rota:** Usa `matchRoute` para verificar se a URI corresponde ao padrão da rota (incluindo parâmetros dinâmicos).
     * - **Execução de Middlewares:** Se houver middlewares e `runMiddlewares` retornar `false` (bloqueio ou erro), o loop é encerrado.
     * - **Preparação:** Instancia o controlador, determina a ação e prepara os parâmetros (`$params`), mesclando os parâmetros dinâmicos da rota com os dados do payload (`$requestData`).
     * - **Invocação:** O método do controlador é invocado (`call_user_func_array`), e a execução é encerrada.
     * 6.  **Rota Não Encontrada:** Se o loop terminar sem encontrar uma rota correspondente, `self::pageNotFound()` é chamado, enviando um erro **404 Not Found**.
     *
     * @return void Este método não retorna um valor, pois seu objetivo é gerenciar o fluxo de controle e enviar a resposta HTTP final, encerrando a execução.
     */
    public static function dispatch(): void {
        self::checkRequiredConstants();

        $method = $_SERVER['REQUEST_METHOD'];
        $uri = trim(string: parse_url(url: $_SERVER['REQUEST_URI'], component: PHP_URL_PATH), characters: '/');

        if ($method === 'POST' && isset($_POST['_method'])) $method = strtoupper(string: $_POST['_method']);
        if (!in_array(needle: $method, haystack: self::$allowedHttpRequests)) self::jsonError(code: 405, msg: "Método HTTP '{$method}' não permitido.");

        self::corsSetup(method: $method);

        if (!empty(self::$basePath) && str_starts_with(haystack: $uri, needle: trim(string: self::$basePath, characters: '/'))) {
            $uri = substr(string: $uri, offset: strlen(string: trim(string: self::$basePath, characters: '/')));
        }
        $uri = trim(string: $uri, characters: '/');

        $requestData = in_array(needle: $method, haystack: ['PUT','DELETE','PATCH']) ? self::extractRequestData(method: $method) : [];

        foreach (self::$routes[$method] ?? [] as $route) {
            if (!self::matchRoute(method: $method, uri: $uri, route: $route)) continue;

            if (!empty($route['middlewares']) && !self::runMiddlewares(middlewares: $route['middlewares'])) return;

            $controller = new $route['controller']();
            $action = $route['action'];
            $params = self::prepareMethodParameters(method: $method, params: [$requestData]);

            if (!method_exists(object_or_class: $controller, method: $action)) self::jsonError(code: 500, msg: "Método {$action} não encontrado.");

            http_response_code(response_code: 200);
            if (self::$ROUTER_MODE === 'JSON') header(header: 'Content-Type: application/json; charset=utf-8');
            call_user_func_array(callback: [$controller, $action], args: $params);
            exit;
        }

        self::pageNotFound();
    }
}