<?php
namespace KrothiumPHP\View;

class FormBuilder {
    /**
     * Renderiza um elemento completo de formulário HTML (input, select ou textarea), incluindo label e mensagens de erro.
     *
     * Este método é uma *factory* de campos de formulário, responsável por:
     * 1. Obter o valor (de 'inputs' no $content ou 'value' no $attrs) e escapar caracteres HTML.
     * 2. Verificar erros de validação ($content['errors']) para aplicar a classe 'is-invalid' e exibir a mensagem de erro.
     * 3. Adicionar um marcador de campo obrigatório (`<sup class="text-danger">*</sup>`) ao label, se o atributo 'required' estiver definido.
     * 4. Montar os atributos e estilos inline para o campo.
     * 5. Gerar a tag <label> e o campo de formulário correspondente ao $type ('text' padrão, 'select' ou 'textarea').
     * 6. Popular as opções do <select> e marcar a opção selecionada com base no $value.
     *
     * @param string $name O nome e o atributo 'name' do campo (usado como fallback para o 'id').
     * @param string $label O rótulo a ser exibido para o campo. Padrão: string vazia.
     * @param string $type O tipo de campo a ser renderizado ('text' [padrão], 'email', 'number', 'select', 'textarea', etc.).
     * @param array $options Para campos do tipo 'select', esta matriz contém os pares valor => rótulo das opções. Padrão: array vazio.
     * @param array $content Uma matriz (array) que se espera conter as chaves 'inputs' (para repopular o valor) e 'errors' (para exibir mensagens de validação). Padrão: array vazio.
     * @param array $attrs Atributos HTML adicionais para o elemento de entrada (ex: 'required' => true, 'placeholder' => '...', 'data-alguma-coisa' => 'valor'). Padrão: array vazio.
     * @param array $style Matriz associativa de propriedades CSS inline para o campo (ex: ['width' => '100px']). Padrão: array vazio.
     * @return string O código HTML completo do campo de formulário (label, input/select/textarea, e mensagem de erro, se houver).
     */
    public static function renderFormField(string $name, string $label = '', string $type = 'text', array $options = [], array $content = [], array $attrs = [], array $style = []): string {
        $value = htmlspecialchars(string: $content['inputs'][$name] ?? $attrs['value'] ?? '');
        $error = $content['errors'][$name]['message'] ?? null;
        $invalidClass = $error ? ' is-invalid' : '';
        // required
        $isRequired = isset($attrs['required']) && $attrs['required'];
        if ($isRequired) {
            $label .= ' <sup class="text-danger">*</sup>';
        }
        // label id
        $labelId = $attrs['label_id'] ?? '';
        $labelHtml = $labelId ? " id='{$labelId}'" : '';
        // input id
        $inputId = $attrs['id'] ?? $name;
        // monta atributos do input
        $attrHtml = '';
        foreach ($attrs as $k => $v) {
            if (in_array(needle: $k, haystack: ['label_id','id'])) continue;
            if (is_bool(value: $v) && $v === true) {
                $attrHtml .= " {$k}";
            } elseif ($v !== false) {
                $attrHtml .= " {$k}=\"{$v}\"";
            }
        }
        // monta CSS inline
        $styleHtml = '';
        if (!empty($style)) {
            $css = '';
            foreach ($style as $prop => $val) {
                $css .= "{$prop}: {$val}; ";
            }
            $styleHtml = " style=\"{$css}\"";
        }
        // monta label
        if (!empty($label)) {
            $html = "<label class='form-label' for='{$inputId}'{$labelHtml}>{$label}</label>";
        } else {
            $html = '';
        }
        // monta o campo
        if ($type === 'select') {
            $html .= "<select class='form-select{$invalidClass}' name='{$name}' id='{$inputId}'{$attrHtml}{$styleHtml}>";
            foreach ($options as $optValue => $optLabel) {
                $selected = ($value == $optValue) ? ' selected' : '';
                $html .= "<option value='{$optValue}'{$selected}>{$optLabel}</option>";
            }
            $html .= "</select>";
        } elseif ($type === 'textarea') {
            $html .= "<textarea class='form-control{$invalidClass}' name='{$name}' id='{$inputId}'{$attrHtml}{$styleHtml}>{$value}</textarea>";
        } else {
            $html .= "<input type='{$type}' class='form-control{$invalidClass}' name='{$name}' id='{$inputId}' value='{$value}'{$attrHtml}{$styleHtml}>";
        }
        // erro
        if ($error) {
            $html .= "<div class='invalid-feedback'>{$error}</div>";
        }
        return $html;
    }

    /**
     * Renderiza um elemento HTML de **"badge"** (etiqueta/rótulo) formatado com cor, rótulo e estilos com base em um valor de entrada e um mapa de configuração.
     *
     * Este método é ideal para exibir o status ou um tipo de item de forma visualmente distinta:
     * 1. Utiliza o `$value` como chave para buscar as configurações (`label` e `color`) no `$map` fornecido.
     * 2. Se a chave não for encontrada no mapa, retorna uma string vazia, evitando a renderização de badges sem contexto.
     * 3. Monta e aplica atributos HTML extras e estilos CSS inline fornecidos.
     * 4. Retorna a tag `<span>` formatada, usando `bg-{color}` para a cor de fundo e o `label` configurado.
     *
     * @param string|int $value O valor que serve como **chave** para buscar a configuração no mapa (ex: um ID de status).
     * @param array $map Uma matriz associativa contendo as configurações dos badges, onde a chave é o $value e o valor é um array com 'label' (rótulo) e 'color' (cor). Ex: [1 => ['label' => 'Ativo', 'color' => 'success']].
     * @param array $attrs Atributos HTML opcionais (ex: 'id' => 'meu-badge', 'class' => 'extra-class') que serão adicionados à tag <span>. Padrão: array vazio.
     * @param array $style Matriz associativa de propriedades CSS inline para o elemento <span> (ex: ['font-size' => '12px']). Padrão: array vazio.
     * @return string O código HTML completo do badge (tag <span> com classes e atributos) se o valor for encontrado no mapa, ou uma string vazia caso contrário.
     */
    public static function renderBadge(string|int $value, array $map, array $attrs = [], array $style = []): string {
        // se o valor não existir no mapa, nem renderiza
        if (!isset($map[$value])) {
            return '';
        }
        // config
        $config = $map[$value];
        $label  = $config['label'] ?? '';
        $color  = $config['color'] ?? 'secondary';
        // atributos extras
        $attrHtml = '';
        foreach ($attrs as $k => $v) {
            if (is_bool(value: $v)) {
                if ($v) $attrHtml .= " {$k}";
            } else {
                $attrHtml .= " {$k}=\"{$v}\"";
            }
        }
        // css inline
        $styleHtml = '';
        if (!empty($style)) {
            $css = '';
            $elementsCounter = 0;
            foreach ($style as $prop => $val) {
                if ($elementsCounter  == 0) {
                    $css .= "{$prop}: {$val};";
                } else {
                    $css .= " {$prop}: {$val};";
                }
                $elementsCounter++;
            }
            $styleHtml = " style=\"{$css}\"";
        }
        return "<span class=\"badge bg-{$color}\"{$attrHtml}{$styleHtml}>{$label}</span>";
    }

    /**
     * Gera a estrutura HTML para uma paginação.
     *
     * Esta função cria os links de navegação para as páginas de resultados, considerando o número total de registros,
     * o limite de registros por página, a página atual e opções de personalização.
     *
     * @param mixed $pagina_atual A página atual.
     * @param int $limite O número máximo de registros por página.
     * @param mixed $total_registros O número total de registros.
     * @param int $max_links O número máximo de links de página a serem exibidos (opcional, padrão: 10).
     * @param string $container_class A classe CSS para o container da paginação (opcional, padrão: 'pagination justify-content-center').
     * @return string O HTML da paginação.
     */
    public static function generatePagination(mixed $pagina_atual, mixed $total_registros, int $limite = 10): string {
        is_null(value: $pagina_atual) ? '1' : $pagina_atual;
        is_null(value: $total_registros) ? '0' : $total_registros;
        // Calcula o total de páginas
        $total_paginas = ceil(num: $total_registros / $limite);
        // Construir a query string com os parâmetros atuais, exceto 'page'
        $query_params = $_GET;
        unset($query_params['page']); // Remove 'page' para evitar duplicação
        unset($query_params['url']); // Remove 'url' para evitar duplicação
        $query_string = http_build_query(data: $query_params);
        // Limitar a quantidade máxima de botões a serem exibidos
        $max_botoes = 10;
        $inicio = max(1, $pagina_atual - intval($max_botoes / 2));
        $fim = min($total_paginas, $inicio + $max_botoes - 1);
        // Ajustar a janela de exibição se atingir o limite inferior ou superior
        if ($fim - $inicio + 1 < $max_botoes) {
            $inicio = max(1, $fim - $max_botoes + 1);
        }
        // Validação das paginações
        if($total_registros > $limite){
            // Inicia a criação do HTML da paginação
            $html = '<nav aria-label="Page navigation">';
            $html .= '<ul class="pagination justify-content-center">';
            // Botão Anterior (desabilitado na primeira página)
            if ($pagina_atual > 1) {
                $anterior = $pagina_atual - 1;
                $html .= '<li class="page-item">';
                $html .= '<a class="page-link" href="?' . $query_string . '&page=' . $anterior . '"><span aria-hidden="true">&laquo;</span></a>';
                $html .= '</li>';
            } else {
                $html .= '<li class="page-item disabled">';
                $html .= '<a class="page-link"><span aria-hidden="true">&laquo;</span></a>';
                $html .= '</li>';
            }
            // Geração dos links de cada página dentro da janela definida
            for ($i = $inicio; $i <= $fim; $i++) {
                if ($i == $pagina_atual) {
                    $html .= '<li class="page-item active">';
                    $html .= '<a class="page-link" href="?' . $query_string . '&page=' . $i . '">' . $i . '</a>';
                    $html .= '</li>';
                } else {
                    $html .= '<li class="page-item">';
                    $html .= '<a class="page-link" href="?' . $query_string . '&page=' . $i . '">' . $i . '</a>';
                    $html .= '</li>';
                }
            }
            // Botão Próximo (desabilitado na última página)
            if ($pagina_atual < $total_paginas) {
                $proxima = $pagina_atual + 1;
                $html .= '<li class="page-item">';
                $html .= '<a class="page-link" href="?' . $query_string . '&page=' . $proxima . '"><span aria-hidden="true">&raquo;</span></a>';
                $html .= '</li>';
            } else {
                $html .= '<li class="page-item disabled">';
                $html .= '<a class="page-link"><span aria-hidden="true">&raquo;</span></a>';
                $html .= '</li>';
            }
            $html .= '</ul>';
            $html .= '</nav>';
        } else {
            $html = "";
        }
        return $html;
    }

    /**
     * Converte um array de registros em opções para <select>.
     *
     * @param array $items Array de dados (ex: resultado do banco).
     * @param string $valueKey Chave que será usada como value do option.
     * @param string|callable $label Chave do label OU uma função para gerar o label.
     * @return array Array no formato [value => label].
     */
    public static function buildSelectOptions(array $items, string $valueKey, string|callable $label): array {
        if (empty($items)) {
            return [];
        }
        // Caso o label seja uma função (closure)
        if (is_callable(value: $label)) {
            return array_column(
                array: array_map(
                    callback: fn ($item) => [
                        'value' => $item[$valueKey],
                        'label' => $label($item),
                    ],
                    array: $items
                ),
                column_key: 'label',
                index_key: 'value'
            );
        }
        // Caso simples: chave direta
        return array_column(array: $items, column_key: $label, index_key: $valueKey);
    }
}
