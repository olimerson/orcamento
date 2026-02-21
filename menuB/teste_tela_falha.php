<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "config.php";
require_once "functions.inc";

$mesAno = $_GET['mesAno'] ?? null;
if (!$mesAno) {
    exit("Ocorrência não informada");
}

$conn = conectar();
if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

/* =====================================================
   BUSCA RECEITA
===================================================== */
$salario = 0;

$sqlReceita = "SELECT receita FROM receita WHERE ocorrencia = ?";
$stmt = $conn->prepare($sqlReceita);
$stmt->bind_param("s", $mesAno);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $salario = $row['receita'];
}
$stmt->close();

/* =====================================================
   BUSCA DESPESAS
===================================================== */
$sql = "
SELECT 
    despesa.*,
    descricao.descricao_abreviada,
    categoria.nome_categoria
FROM despesa
JOIN descricao ON despesa.id_descricao = descricao.id_descricao
JOIN categoria ON despesa.categoria = categoria.categoria
WHERE despesa.ocorrencia = ?
  AND despesa.valido = 'S'
ORDER BY despesa.id_descricao, despesa.parcela ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $mesAno);
$stmt->execute();
$result = $stmt->get_result();
$despesas = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* =====================================================
   VARIÁVEIS DE CONTROLE
===================================================== */
$descricaoAtual = null;
$descricaoNome  = '';
$subtotalDescricao = 0;
$resta_pagar = 0;
$totalGeral  = 0;
// IDs que devem ser ignorados nos totais
$idsIgnorados = [34,35,36,37];
// 33 despesa VA
// 34 aluguel br
// 35 condominio br
// 36 energia br
// 37 agua br

?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Despesas Agrupadas</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #e4fccb;
}

.balao {
    background: #d7e7b9;
    border: 1px solid #000;
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 20px;
    box-shadow: 4px 4px 0 #000;
}

.balao-titulo {
    font-size: 18px;
    font-weight: bold;
    border-bottom: 1px solid #000;
    padding-bottom: 6px;
    margin-bottom: 10px;
}

.balao-linha {
    display: grid;
    grid-template-columns:
        300px
        30px
        110px
        110px
        110px
        70px
        140px
        100px
        80px;
    gap: 6px;
    padding: 6px 0;
    border-bottom: 1px dotted #999;
    align-items: center;
}

.balao-linha:last-child {
    border-bottom: none;
}

.valor {
    text-align: right;
}

.pago {
    color: green;
    font-weight: bold;
}

.nao-pago {
    color: red;
    font-weight: bold;
}

.balao-total {
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid #000;
    font-weight: bold;
    text-align: right;
}

.btn-editar {
    padding: 4px 8px;
    border-radius: 6px;
    border: 1px solid #000;
    background: #ddd;
    cursor: pointer;
}

#totalMarcado {
    position: fixed;
    bottom: 450px;
    right: 1080px;
    background-color: #886161;
    color: white;
    padding: 6px 18px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    box-shadow: 0 4px 6px rgba(0,0,0,.2);
}
</style>
</head>

<body>

<?php
/* =====================================================
   LOOP PRINCIPAL
===================================================== */
foreach ($despesas as $d) {

    $id = (int)$d['id_descricao'];
    $ignorar = in_array($id, $idsIgnorados, true);

        /* -------- TOTAIS GERAIS -------- */

        // Resta pagar
        // verifica se a despesa está marcada como "não paga" e não é uma das descrições ignoradas
        if ($d['pago'] === '2001-01-01' && !$ignorar) {
            $resta_pagar += $d['valor'];
        }

        // Total geral
        // Somente soma se a descrição não estiver na lista de ignorados
        if (!$ignorar) {
            $totalGeral += $d['valor'];
        }


        if ($descricaoAtual !== null && $descricaoAtual != $id) {

        echo "
        <div class='balao-total'>
                <!-- Exibe o total do balão anterior -->
                    Total {$descricaoNome} → R$ " . number_format($subtotalDescricao, 2, ',', '.') . "
                </div>
            </div>";

            $subtotalDescricao = 0;
        }




       if ($descricaoAtual != $id) {
        echo "
        <div class='balao'>
            <div class='balao-titulo'>
                {$d['descricao_abreviada']}
            </div>";

        $descricaoAtual = $id;
        $descricaoNome  = $d['descricao_abreviada'];
        }

   /* -------- STATUS -------- */
    // Verifica se a despesa está paga ou não
    // Se o sistema encontrar a data padrão (2001-01-01), ele exibe um aviso de "N Pago" com uma classe CSS específica
    // (provavelmente para ficar vermelho). Caso contrário, exibe "Pago" (provavelmente em verde)
    $statusPago = ($d['pago'] === '2001-01-01')
        ? "<span class='nao-pago'>N Pago</span>"
        : "<span class='pago'>Pago</span>";
    /* --------FIM DO STATUS -------- */



        /* -------- LINHA -------- */
            echo "
            <div class='balao-linha'>
                <div>{$d['despesa']}</div>
                <input type='checkbox' class='check' value='{$d['valor']}'>                
                <div class='valor'>R$ " . number_format($d['valor'], 2, ',', '.') . "</div>
                <div>{$statusPago}</div>
            </div>";
            $subtotalDescricao += $d['valor'];

}
        /* -------- FECHA ÚLTIMO BALÃO -------- */
        if ($descricaoAtual !== null) {
            echo "
                <div class='balao-total'>
                    Total {$descricaoNome} → R$ " . number_format($subtotalDescricao, 2, ',', '.') . "
                </div>
            </div>";
        }
?>

<!-- TOTAIS FINAIS -->
<div class="balao">
    <div class="balao-total">
        Resta pagar → <strong>R$ <?= number_format($resta_pagar, 2, ',', '.') ?></strong><br>
        Total geral → <strong>R$ <?= number_format($totalGeral, 2, ',', '.') ?></strong><br>
        Salário mês → <strong>R$ <?= number_format($salario, 2, ',', '.') ?></strong><br>
        Saldo → <strong>R$ <?= number_format($salario - $totalGeral, 2, ',', '.') ?></strong>
    </div>
</div>