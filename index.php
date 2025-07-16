<?php

// ==================================================
// BACKEND PHP
// ==================================================

class Ingrediente {
    public $nome;
    public $carboidrato_por_g;
    public $proteina_por_g;
    public $gordura_por_g;
    public $fibra_por_g;
    public $sodio_por_g;

    public function __construct($nome, $carb, $prot, $gord, $fibra, $sodio) {
        $this->nome = $nome;
        $this->carboidrato_por_g = $carb;
        $this->proteina_por_g = $prot;
        $this->gordura_por_g = $gord;
        $this->fibra_por_g = $fibra;
        $this->sodio_por_g = $sodio;
    }

    public function toArray() {
        return [
            'nome' => $this->nome,
            'carboidrato_por_g' => $this->carboidrato_por_g,
            'proteina_por_g' => $this->proteina_por_g,
            'gordura_por_g' => $this->gordura_por_g,
            'fibra_por_g' => $this->fibra_por_g,
            'sodio_por_g' => $this->sodio_por_g
        ];
    }
}

class Receita {
    public $nome;
    public $ingredientes;
    public $rendimento_total;

    public function __construct($nome, $ingredientes, $rendimento) {
        $this->nome = $nome;
        $this->ingredientes = $ingredientes;
        $this->rendimento_total = $rendimento;
    }

    public function toArray() {
        return [
            'nome' => $this->nome,
            'ingredientes' => array_map(function($item) {
                return [$item[0]->nome, $item[1]];
            }, $this->ingredientes),
            'rendimento_total' => $this->rendimento_total
        ];
    }
}

class DataManager {
    const INGREDIENTES_FILE = 'ingredientes.json';
    const RECEITAS_FILE = 'receitas.json';
    const VD_FILE = 'valores_diarios.json';

    public static function carregarIngredientes() {
        if (!file_exists(self::INGREDIENTES_FILE)) return [];
        $json = file_get_contents(self::INGREDIENTES_FILE);
        $dados = json_decode($json, true);
        
        return array_map(function($item) {
            return new Ingrediente(
                $item['nome'],
                $item['carboidrato_por_g'],
                $item['proteina_por_g'],
                $item['gordura_por_g'],
                $item['fibra_por_g'],
                $item['sodio_por_g']
            );
        }, $dados);
    }

    public static function salvarIngredientes($ingredientes) {
        $dados = array_map(function($ing) {
            return $ing->toArray();
        }, $ingredientes);
        
        file_put_contents(self::INGREDIENTES_FILE, json_encode($dados, JSON_PRETTY_PRINT));
    }

    public static function atualizarIngrediente($nomeOriginal, $novosDados) {
    $ingredientes = self::carregarIngredientes();
    foreach ($ingredientes as $ing) {
        if ($ing->nome === $nomeOriginal) {
            $ing->nome = $novosDados['nome'];
            $ing->carboidrato_por_g = $novosDados['carb'];
            $ing->proteina_por_g = $novosDados['prot'];
            $ing->gordura_por_g = $novosDados['gord'];
            $ing->fibra_por_g = $novosDados['fibra'];
            $ing->sodio_por_g = $novosDados['sodio'];
            break;
        }
    }
    self::salvarIngredientes($ingredientes);
    }
    
    public static function carregarReceitas($ingredientes) {
        try {
            if (!file_exists(self::RECEITAS_FILE)) return [];
            
            $json = file_get_contents(self::RECEITAS_FILE);
            $dados = json_decode($json, true);
            
            $receitas = [];
            foreach ($dados as $item) {
                $ingredientesReceita = [];
                foreach ($item['ingredientes'] as $ing) {
                    $nomeIng = $ing[0];
                    $qtd = $ing[1];
                    
                    // Procurar ingrediente pelo nome
                    $ingredienteEncontrado = null;
                    foreach ($ingredientes as $ingObj) {
                        if ($ingObj->nome === $nomeIng) {
                            $ingredienteEncontrado = $ingObj;
                            break;
                        }
                    }
                    
                    if ($ingredienteEncontrado) {
                        $ingredientesReceita[] = [$ingredienteEncontrado, $qtd];
                    }
                }
                
                // Calcular rendimento total se não existir
                $rendimento = $item['rendimento_total'] ?? array_reduce(
            $item['ingredientes'],
        function($total, $ing) {
                    return $total + $ing[1];
                    },
                    0
                );
                        
                $receitas[] = new Receita(
                    $item['nome'],
                    $ingredientesReceita,
                    $rendimento
                );
            }
            return $receitas;
            
        } catch (Exception $e) {
            error_log("Erro ao carregar receitas: " . $e->getMessage());
            return [];
        }
    }

    public static function salvarReceitas($receitas) {
        try {
            $dados = array_map(function($r) {
                return [
                    'nome' => $r->nome,
                    'ingredientes' => array_map(function($ing) {
                        return [$ing[0]->nome, $ing[1]];
                    }, $r->ingredientes),
                    'rendimento_total' => $r->rendimento_total
                ];
            }, $receitas);
            
            file_put_contents(
                self::RECEITAS_FILE,
                json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            
        } catch (Exception $e) {
            error_log("Erro ao salvar receitas: " . $e->getMessage());
        }
    }

    public static function deletarIngrediente($nome) {
        $ingredientes = self::carregarIngredientes();
        $ingredientes = array_filter($ingredientes, function($i) use ($nome) {
            return $i->nome !== $nome;
        });
        self::salvarIngredientes(array_values($ingredientes));
    }

    public static function deletarReceita($nome) {
        $ingredientes = self::carregarIngredientes();
        $receitas = self::carregarReceitas($ingredientes);
        $receitas = array_filter($receitas, function($r) use ($nome) {
            return $r->nome !== $nome;
        });
        self::salvarReceitas(array_values($receitas));
    }
}

class CalculadoraNutricional {
    public static function calcularPorPorcao($receitasPorcoes) {
        $totais = [
            'carboidratos' => 0.0,
            'proteina' => 0.0,
            'gordura' => 0.0,
            'fibra' => 0.0,
            'sodio' => 0.0,
            'calorias' => 0.0
        ];

        foreach ($receitasPorcoes as list($receita, $porcao)) {
            if ($receita->rendimento_total <= 0) {
                throw new InvalidArgumentException(
                    "Receita {$receita->nome} tem rendimento total inválido"
                );
            }

            $fator = $porcao / $receita->rendimento_total;

            foreach ($receita->ingredientes as list($ingrediente, $qtd)) {
                $quantidadeEfetiva = $qtd * $fator;
                
                $totais['carboidratos'] += $quantidadeEfetiva * $ingrediente->carboidrato_por_g;
                $totais['proteina'] += $quantidadeEfetiva * $ingrediente->proteina_por_g;
                $totais['gordura'] += $quantidadeEfetiva * $ingrediente->gordura_por_g;
                
                // Cálculo das calorias
                $totais['calorias'] += (
                    ($quantidadeEfetiva * $ingrediente->carboidrato_por_g * 4) +
                    ($quantidadeEfetiva * $ingrediente->proteina_por_g * 4) +
                    ($quantidadeEfetiva * $ingrediente->gordura_por_g * 9)
                );
                
                $totais['fibra'] += $quantidadeEfetiva * $ingrediente->fibra_por_g;
                $totais['sodio'] += $quantidadeEfetiva * $ingrediente->sodio_por_g;
            }
        }

        return array_map(function($valor) {
            return round($valor, 2);
        }, $totais);
    }
}

class ValoresDiarios {
    const VD_FILE = 'valores_diarios.json';
    
    public static function carregar() {
        if (!file_exists(self::VD_FILE)) {
            self::salvar(self::valoresPadrao());
        }
        $json = file_get_contents(self::VD_FILE);
        return json_decode($json, true);
    }
    
    public static function salvar($dados) {
        $dados_validos = array_merge(self::valoresPadrao(), $dados);
        file_put_contents(
            self::VD_FILE,
            json_encode($dados_validos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
    
    private static function valoresPadrao() {
        return [
            "carboidratos" => 300,
            "proteinas" => 75,
            "gorduras_totais" => 55,
            "valor_energetico" => 2000,
            "fibras" => 30,
            "sodio" => 5000
        ];
    }
}

require 'fpdf/fpdf.php';

class GeradorPDF extends FPDF {
    private $dadosNutricionais;
    private $pesoPorcao;
    private $vd;

    public function gerarTabelaNutricional($nomeArquivo, $dadosNutricionais, $pesoPorcao) {
        $this->dadosNutricionais = $dadosNutricionais;
        $this->pesoPorcao = $pesoPorcao;
        $this->vd = ValoresDiarios::carregar();

        $this->AddPage();
        $this->SetFont('Helvetica', 'B', 16);
        $this->criarCabecalho();
        $this->criarTabela();
        $this->criarRodape();

        // Verificação adicional
        if (!is_array($dadosNutricionais) || empty($dadosNutricionais)) {
            throw new InvalidArgumentException("Dados nutricionais inválidos");
        }

        if ($this->pesoPorcao <= 0) {
            throw new InvalidArgumentException("Peso da porção inválido");
        }

    }

    private function criarCabecalho() {
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetFillColor(240, 240, 240);
        
        // Usando iconv como substituto
        $texto = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 
        "INFORMAÇÃO NUTRICIONAL\nMarmita de {$this->pesoPorcao}g");
        
        $this->MultiCell(105,6,$texto,1,'C',true);
    }

    private function criarTabela() {
        $kcal = $this->dadosNutricionais['calorias'];
        $kj = round($kcal * 4.184); // Arredonda para inteiro
        $porcentagens = $this->calcularPorcentagens($kcal);

        // Ajuste preciso das larguras das colunas
        $colWidths = [35, 50, 20]; 
        $altura = 7; // Altura da linha
        // Configurações gerais
        $this->SetFillColor(245, 245, 220); // Cor bege
        $this->SetFont('Helvetica', '', 10);

        // Cabeçalho da tabela (centralizado)
        $this->Cell($colWidths[0], $altura, (''), 1, 0, 'C', true);
        $this->Cell($colWidths[1], $altura, ('Quantidade nesta marmita'), 1, 0, 'C', true);
        $this->Cell($colWidths[2], $altura, ('%VD*'), 1, 1, 'C', true);

        // Configuração de texto com caracteres especiais
            // Valor Energético
        $texto1 = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "Valor Energético");
            // Proteína
        $texto2 = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "Proteínas");
            // Sódio
        $texto3 = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "Sódio");

        // Dados (alinhamento personalizado)
        $this->linhaTabela($texto1, 
        number_format($kcal, 1) . ' kcal = ' . number_format($kj, 0) . ' kJ', 
        $porcentagens['kcal'],
        $colWidths
        );
        $this->linhaTabela('Carboidratos', 
            number_format($this->dadosNutricionais['carboidratos'], 1) . ' g', 
            $porcentagens['carboidratos'],
            $colWidths,
        );
        $this->linhaTabela($texto2, 
            number_format($this->dadosNutricionais['proteina'], 1) . ' g', 
            $porcentagens['proteina'],
            $colWidths
        );
        $this->linhaTabela('Gorduras Totais', 
            number_format($this->dadosNutricionais['gordura'], 1) . ' g', 
            $porcentagens['gordura'],
            $colWidths
        );
        $this->linhaTabela('Fibra Alimentar', 
            number_format($this->dadosNutricionais['fibra'], 1) . ' g', 
            $porcentagens['fibra'],
            $colWidths
        );
        $this->linhaTabela($texto3, 
            number_format($this->dadosNutricionais['sodio'], 1) . ' mg', 
            $porcentagens['sodio'],
            $colWidths
        );
    }

    private function linhaTabela($nutriente, $valor, $porcentagem, $colWidths) {
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(0); // Preto
        // Linha normal sem fundo
        $this->Cell($colWidths[0], 7, ($nutriente), 'LRBT', 0, 'C');
        $this->Cell($colWidths[1], 7, $valor, 'LRBT', 0, 'C');
        $this->Cell($colWidths[2], 7, number_format($porcentagem, 1) . '%', 'LRBT', 1, 'C');
    }

    private function calcularPorcentagens($kcal) {
        return [
            'carboidratos' => ($this->dadosNutricionais['carboidratos'] / $this->vd['carboidratos']) * 100,
            'proteina' => ($this->dadosNutricionais['proteina'] / $this->vd['proteinas']) * 100,
            'gordura' => ($this->dadosNutricionais['gordura'] / $this->vd['gorduras_totais']) * 100,
            'kcal' => ($kcal / $this->vd['valor_energetico']) * 100,
            'fibra' => ($this->dadosNutricionais['fibra'] / $this->vd['fibras']) * 100,
            'sodio' => ($this->dadosNutricionais['sodio'] / $this->vd['sodio']) * 100
        ];
    }

    private function criarRodape() {
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(0);
        
        $textorodape = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 
        "*% Valores Diários de referência com base em uma dieta de "
            . $this->vd['valor_energetico'] . " kcal ou "
            . number_format($this->vd['valor_energetico'] * 4.184) . " kJ. "
            . "Seus valores diários podem ser maiores ou menores dependendo de suas necessidades energéticas.");
        $this->MultiCell(105, 5, $textorodape,1, 'L');
    }
}

$vd = ValoresDiarios::carregar();
$dataManager = new DataManager();
$ingredientes = $dataManager->carregarIngredientes();
$ingrediente_editando = null;
$receitas = $dataManager->carregarReceitas($ingredientes);

// Processar edição de ingrediente
if (isset($_GET['editar_ingrediente'])) {
    $nomeOriginal = urldecode($_GET['editar_ingrediente']);
    foreach ($ingredientes as $ing) {
        if ($ing->nome === $nomeOriginal) {
            $ingrediente_editando = $ing;
            break;
        }
    }
}

// Processar exclusão de ingrediente
if (isset($_GET['deletar_ingrediente'])) {
    try {
        $nome = urldecode($_GET['deletar_ingrediente']);
        DataManager::deletarIngrediente($nome);
        header('Location: '.strtok($_SERVER['REQUEST_URI'], '?'));
        exit();
    } catch (Exception $e) {
        $alert = ['type' => 'danger', 'message' => 'Erro ao excluir: '.$e->getMessage()];
    }
}

// Processar salvamento de ingrediente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_ingrediente'])) {
    try {
        $novoIngrediente = [
            'nome' => $_POST['nome'],
            'carb' => (float)$_POST['carb'],
            'prot' => (float)$_POST['prot'],
            'gord' => (float)$_POST['gord'],
            'fibra' => (float)$_POST['fibra'],
            'sodio' => (float)$_POST['sodio']
        ];

        if (!empty($_POST['nome_original'])) {
            // Modo edição
            DataManager::atualizarIngrediente($_POST['nome_original'], $novoIngrediente);
        } else {
            // Modo novo
            $ingredientes[] = new Ingrediente(
                $novoIngrediente['nome'],
                $novoIngrediente['carb'],
                $novoIngrediente['prot'],
                $novoIngrediente['gord'],
                $novoIngrediente['fibra'],
                $novoIngrediente['sodio']
            );
            DataManager::salvarIngredientes($ingredientes);
        }
        
        header('Location: '.$_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        $alert = ['type' => 'danger', 'message' => 'Erro: '.$e->getMessage()];
    }
}

// Processar exclusão de receita
if (isset($_GET['deletar_receita'])) {
    try {
        $nome = urldecode($_GET['deletar_receita']);
        DataManager::deletarReceita($nome);
        header('Location: '.strtok($_SERVER['REQUEST_URI'], '?'));
        exit();
    } catch (Exception $e) {
        $alert = ['type' => 'danger', 'message' => 'Erro ao excluir: '.$e->getMessage()];
    }
}

// Processar edição de receita
$receita_editando = null;
if (isset($_GET['editar_receita'])) {
    $nomeReceita = urldecode($_GET['editar_receita']);
    foreach ($receitas as $receita) {
        if ($receita->nome === $nomeReceita) {
            $receita_editando = $receita;
            break;
        }
    }
}

// Processar salvamento de receita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_receita'])) {
    try {
        // Remover receita original se estiver editando
        if (!empty($_POST['nome_original'])) {
            $nomeOriginal = $_POST['nome_original'];
            $receitas = array_filter($receitas, function($r) use ($nomeOriginal) {
                return $r->nome !== $nomeOriginal;
            });
        }
        
        // Criar nova receita
        $ingredientesReceita = [];
        foreach ($_POST['ingredientes'] as $index => $nomeIngrediente) {
            $ingredienteEncontrado = null;
            foreach ($ingredientes as $ing) {
                if ($ing->nome === $nomeIngrediente) {
                    $ingredienteEncontrado = $ing;
                    break;
                }
            }
            
            if ($ingredienteEncontrado) {
                $ingredientesReceita[] = [
                    $ingredienteEncontrado,
                    (float)$_POST['quantidades'][$index]
                ];
            }
        }
        
        $novaReceita = new Receita(
            $_POST['nome_receita'],
            $ingredientesReceita,
            (float)$_POST['rendimento']
        );
        
        $receitas[] = $novaReceita;
        DataManager::salvarReceitas($receitas);
        
        header('Location: '.$_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        $alert = ['type' => 'danger', 'message' => 'Erro: '.$e->getMessage()];
    }
}

// Processar salvamento dos Valores Diários
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_vd'])) {
    try {
        $novos_vd = [
            'carboidratos' => (float)$_POST['vd_carb'],
            'proteinas' => (float)$_POST['vd_prot'],
            'gorduras_totais' => (float)$_POST['vd_gord'],
            'fibras' => (float)$_POST['vd_fibra'],
            'sodio' => (float)$_POST['vd_sodio'],
            'valor_energetico' => (float)$_POST['vd_kcal']
        ];
        
        ValoresDiarios::salvar($novos_vd);
        $vd = $novos_vd; // Atualizar localmente
        
        $alert_vd = ['type' => 'success', 'message' => 'Valores atualizados com sucesso!'];
        
    } catch (Exception $e) {
        $alert_vd = ['type' => 'danger', 'message' => 'Erro: '.$e->getMessage()];
    }
}

// Processar cálculo nutricional e geração de PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calcular'])) {
    try {
        $receitasPorcoes = [];
        $peso_total = 0;
        
        foreach ($_POST['receitas'] as $index => $nomeReceita) {
            foreach ($receitas as $receita) {
                if ($receita->nome === $nomeReceita) {
                    $porcao = (float)$_POST['porcoes'][$index];
                    $receitasPorcoes[] = [$receita, $porcao];
                    $peso_total += $porcao;
                }
            }
        }
        
        if (empty($receitasPorcoes)) {
            throw new Exception("Selecione pelo menos uma receita");
        }
        
        $resultado = CalculadoraNutricional::calcularPorPorcao($receitasPorcoes);
        
        // Gerar PDF diretamente
        $pdf = new GeradorPDF();
        $pdf->gerarTabelaNutricional('', $resultado, $peso_total);
        $pdf->Output('I', 'TabelaNutricional.pdf');
        exit(); // Interrompe a execução
        
    } catch (Exception $e) {
        $alert = ['type' => 'danger', 'message' => $e->getMessage()];
    }
}
// ==================================================
// FRONTEND HTML BÁSICO
// ==================================================
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Nutricional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .tab-content { padding: 20px 0; }
        .nutri-table { max-width: 600px; margin: auto; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="my-4">Sistema de Análise Nutricional Gainz Foods</h2>
        
        <!-- Alertas -->
        <?php if(isset($alert)): ?>
        <div class="alert alert-<?= $alert['type'] ?>"><?= $alert['message'] ?></div>
        <?php endif; ?>

        <!-- Navegação -->
        <nav>
            <div class="nav nav-tabs" id="nav-tab">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ingredientes">Ingredientes</button>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#receitas">Receitas</button>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#vd">Valores Diários</button>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#calcular">Calcular Nutrição</button>
            </div>
        </nav>

        <div class="tab-content">
            <!-- Aba Ingredientes -->
            <div class="tab-pane fade show active" id="ingredientes">
                <h4>Gerenciar Ingredientes</h4>
                
                <!-- Formulário de Ingrediente -->
                <form method="POST" class="row g-3 mb-4">
                    <?php if ($ingrediente_editando): ?>
                        <input type="hidden" name="nome_original" value="<?= htmlspecialchars($ingrediente_editando->nome) ?>">
                    <?php endif; ?>
                    
                    <div class="col-md-4">
                        <input type="text" name="nome" class="form-control" 
                            value="<?= htmlspecialchars($ingrediente_editando->nome ?? '') ?>" 
                            placeholder="Nome do Ingrediente" required>
                    </div>
                    
                    <div class="col-md-2">
                        <input type="number" step="0.01" name="carb" class="form-control" 
                            value="<?= $ingrediente_editando->carboidrato_por_g ?? '' ?>" 
                            placeholder="Carboidratos (g/g)" required>
                    </div>
                    
                    <div class="col-md-2">
                        <input type="number" step="0.01" name="prot" class="form-control" 
                            value="<?= $ingrediente_editando->proteina_por_g ?? '' ?>" 
                            placeholder="Proteínas (g/g)" required>
                    </div>
                    
                    <div class="col-md-2">
                        <input type="number" step="0.01" name="gord" class="form-control" 
                            value="<?= $ingrediente_editando->gordura_por_g ?? '' ?>" 
                            placeholder="Gorduras (g/g)" required>
                    </div>
                    
                    <div class="col-md-2">
                        <input type="number" step="0.01" name="fibra" class="form-control" 
                            value="<?= $ingrediente_editando->fibra_por_g ?? '' ?>" 
                            placeholder="Fibras (g/g)" required>
                    </div>
                    
                    <div class="col-md-2">
                        <input type="number" step="0.01" name="sodio" class="form-control" 
                            value="<?= $ingrediente_editando->sodio_por_g ?? '' ?>" 
                            placeholder="Sódio (mg/g)" required>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" name="salvar_ingrediente" class="btn btn-primary">
                            <?= $ingrediente_editando ? 'Atualizar' : 'Salvar' ?>
                        </button>
                        <?php if ($ingrediente_editando): ?>
                            <a href="?" class="btn btn-secondary mt-2">Cancelar Edição</a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Lista de Ingredientes -->
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Carboidratos</th>
                            <th>Proteínas</th>
                            <th>Gorduras</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ingredientes as $ing): ?>
                        <tr>
                            <td><?= $ing->nome ?></td>
                            <td><?= $ing->carboidrato_por_g ?> g/g</td>
                            <td><?= $ing->proteina_por_g ?> g/g</td>
                            <td><?= $ing->gordura_por_g ?> g/g</td>
                            <td>
                                <a href="?editar_ingrediente=<?= urlencode($ing->nome) ?>" class="btn btn-sm btn-warning">Editar</a>
                                <a href="?deletar_ingrediente=<?= urlencode($ing->nome) ?>" 
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Tem certeza que deseja excluir este ingrediente?')">Excluir</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Aba Receitas -->
            <div class="tab-pane fade" id="receitas">
                <h4>Gerenciar Receitas</h4>
                
                <!-- Formulário de Receita -->
                <form method="POST" class="mb-4">
                    <?php if ($receita_editando): ?>
                        <input type="hidden" name="nome_original" value="<?= htmlspecialchars($receita_editando->nome) ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="nome_receita" class="form-control" 
                                value="<?= htmlspecialchars($receita_editando->nome ?? '') ?>" 
                                placeholder="Nome da Receita" required>
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="rendimento" class="form-control" 
                                value="<?= $receita_editando->rendimento_total ?? '' ?>" 
                                placeholder="Rendimento total (g)" required>
                        </div>
                    </div>
                    
                    <div id="ingredientes-receita" class="mt-3">
                        <?php if ($receita_editando): ?>
                            <?php foreach ($receita_editando->ingredientes as $ing): ?>
                                <div class="row g-3 ingrediente-item mb-2">
                                    <div class="col-md-6">
                                        <select class="form-select" name="ingredientes[]" required>
                                            <?php foreach($ingredientes as $ingGeral): ?>
                                                <option value="<?= htmlspecialchars($ingGeral->nome) ?>" 
                                                    <?= $ingGeral->nome === $ing[0]->nome ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($ingGeral->nome) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="number" step="1" name="quantidades[]" 
                                            value="<?= $ing[1] ?>" 
                                            class="form-control" placeholder="Quantidade (g)" required>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="row g-3 ingrediente-item mb-2">
                                <div class="col-md-6">
                                    <select class="form-select" name="ingredientes[]" required>
                                        <?php foreach($ingredientes as $ing): ?>
                                            <option value="<?= htmlspecialchars($ing->nome) ?>">
                                                <?= htmlspecialchars($ing->nome) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="number" step="1" name="quantidades[]" 
                                        class="form-control" placeholder="Quantidade (g)" required>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" id="add-ingrediente" class="btn btn-secondary mt-2">
                        + Adicionar Ingrediente
                    </button>
                    <button type="submit" name="salvar_receita" class="btn btn-primary mt-2">
                        <?= $receita_editando ? 'Atualizar Receita' : 'Salvar Receita' ?>
                    </button>
                    <?php if ($receita_editando): ?>
                        <a href="?" class="btn btn-secondary mt-2">Cancelar Edição</a>
                    <?php endif; ?>
                </form>

                <!-- Lista de Receitas -->
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Ingredientes</th>
                            <th>Rendimento</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($receitas as $r): ?>
                        <tr>
                            <td><?= $r->nome ?></td>
                            <td>
                                <?php foreach($r->ingredientes as $ing): ?>
                                <?= $ing[0]->nome ?> (<?= $ing[1] ?>g)<br>
                                <?php endforeach; ?>
                            </td>
                            <td><?= $r->rendimento_total ?>g</td>
                            <td>
                                <a href="?editar_receita=<?= urlencode($r->nome) ?>" class="btn btn-sm btn-warning">Editar</a>
                                <a href="?deletar_receita=<?= urlencode($r->nome) ?>" 
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Tem certeza que deseja excluir esta receita?')">
                                    Excluir
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Aba Valores Diários -->
            <div class="tab-pane fade" id="vd">
                <h4>Valores Diários de Referência</h4>
                <?php if(isset($alert_vd)): ?>
                    <div class="alert alert-<?= $alert_vd['type'] ?>"><?= $alert_vd['message'] ?></div>
                <?php endif; ?>
                
                <form method="POST" class="nutri-table">
                    <input type="hidden" name="salvar_vd" value="1">
                    
                    <div class="mb-3">
                        <label>Valor Energético (kcal)</label>
                        <input type="number" step="1" name="vd_kcal" class="form-control" 
                            value="<?= htmlspecialchars($vd['valor_energetico'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label>Carboidratos (g)</label>
                        <input type="number" step="1" name="vd_carb" class="form-control" 
                            value="<?= htmlspecialchars($vd['carboidratos'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label>Proteínas (g)</label>
                        <input type="number" step="1" name="vd_prot" class="form-control" 
                            value="<?= htmlspecialchars($vd['proteinas'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label>Gorduras Totais (g)</label>
                        <input type="number" step="1" name="vd_gord" class="form-control" 
                            value="<?= htmlspecialchars($vd['gorduras_totais'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label>Fibras (g)</label>
                        <input type="number" step="1" name="vd_fibra" class="form-control" 
                            value="<?= htmlspecialchars($vd['fibras'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label>Sódio (mg)</label>
                        <input type="number" step="1" name="vd_sodio" class="form-control" 
                            value="<?= htmlspecialchars($vd['sodio'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Salvar Valores</button>
                </form>
            </div>
            <!-- Aba Calcular Nutrição -->
            <div class="tab-pane fade" id="calcular">
                <h4>Calculadora Nutricional</h4>
                
                <form method="POST">
                    <div id="receitas-porcao">
                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <select class="form-select" name="receitas[]" required>
                                    <?php foreach($receitas as $r): ?>
                                        <option value="<?= htmlspecialchars($r->nome) ?>">
                                            <?= htmlspecialchars($r->nome) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="number" step="1" name="porcoes[]" 
                                    class="form-control" placeholder="Porção (g)" required>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" id="add-receita" class="btn btn-secondary mt-2">
                        + Adicionar Receita
                    </button>
                    
                    <!-- Botão único para gerar PDF -->
                    <button type="submit" name="calcular" class="btn btn-success mt-2">
                        Gerar PDF
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Controle de abas persistente
        (function() {
            // Inicialização após DOM carregado
            document.addEventListener('DOMContentLoaded', function() {
                // Configurar abas
                const triggerTabList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tab"]'));
                
                // Restaurar aba salva
                const savedTab = localStorage.getItem('activeTab');
                if (savedTab) {
                    const targetTab = document.querySelector(savedTab);
                    if (targetTab) {
                        new bootstrap.Tab(targetTab).show();
                    }
                }

                // Salvar estado ao mudar de aba
                triggerTabList.forEach(function (triggerEl) {
                    triggerEl.addEventListener('shown.bs.tab', function (event) {
                        localStorage.setItem('activeTab', event.target.getAttribute('data-bs-target'));
                    });
                });

                // Forçar atualização após submit
                document.querySelectorAll('form').forEach(form => {
                    form.addEventListener('submit', () => {
                        const activeTab = document.querySelector('.nav-link.active[data-bs-toggle="tab"]');
                        if (activeTab) {
                            localStorage.setItem('activeTab', activeTab.getAttribute('data-bs-target'));
                        }
                    });
                });
            });

            // Funções de manipulação dinâmica
            document.getElementById('add-ingrediente').addEventListener('click', function() {
                const clone = document.querySelector('.ingrediente-item').cloneNode(true);
                clone.querySelectorAll('input').forEach(input => input.value = '');
                document.getElementById('ingredientes-receita').appendChild(clone);
            });

            document.getElementById('add-receita').addEventListener('click', function() {
                const novoItem = document.querySelector('#receitas-porcao .row').cloneNode(true);
                novoItem.querySelectorAll('input').forEach(input => input.value = '');
                document.getElementById('receitas-porcao').appendChild(novoItem);
            });

            // Geração de PDF
            document.getElementById('form-pdf')?.addEventListener('submit', function(e) {
                e.preventDefault();
                const form = this;
                
                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form)
                })
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'tabela_nutricional.pdf';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                })
                .catch(error => console.error('Erro:', error));
            });
        })();

        // Forçar recarregamento suave
        window.addEventListener('beforeunload', function() {
            localStorage.removeItem('activeTab');
        });
    </script>
</body>
</html>