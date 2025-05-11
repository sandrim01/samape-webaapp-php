<?php
/**
 * SAMAPE - About Page
 * Displays information about the company and system
 */

// Page title
$page_title = "Sobre a SAMAPE";
$page_description = "Conheça nossa história e missão";

// Include initialization file
require_once 'config/init.php';

// Require user to be logged in
require_login();

// Include page header
include_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Sobre a SAMAPE</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="about-section">
                    <h4>Nossa História</h4>
                    <p>A SAMAPE - Serviço de Assistência e Manutenção de Maquinário Pesado, foi fundada em 2010 com o objetivo de oferecer serviços especializados de manutenção, reparo e assistência técnica para equipamentos industriais e maquinário pesado.</p>
                    
                    <p>Desde o início, nosso compromisso tem sido proporcionar atendimento de excelência, garantindo a segurança e eficiência operacional dos equipamentos de nossos clientes.</p>
                    
                    <p>Ao longo dos anos, expandimos nossos serviços e hoje atendemos diversos setores da indústria, incluindo construção civil, mineração, agricultura e logística.</p>
                    
                    <h4 class="mt-4">Missão</h4>
                    <p>Oferecer soluções de manutenção e assistência técnica com excelência, garantindo a operação segura e eficiente dos equipamentos de nossos clientes, contribuindo para a produtividade e sustentabilidade dos seus negócios.</p>
                    
                    <h4 class="mt-4">Visão</h4>
                    <p>Ser reconhecida como referência nacional em serviços de manutenção de maquinário pesado, destacando-se pela qualidade técnica, inovação e compromisso com a satisfação dos clientes.</p>
                    
                    <h4 class="mt-4">Valores</h4>
                    <ul>
                        <li><strong>Excelência técnica:</strong> Buscamos constantemente o aprimoramento técnico e a qualidade em tudo o que fazemos.</li>
                        <li><strong>Segurança:</strong> Priorizamos a segurança de nossos colaboradores e clientes em todas as operações.</li>
                        <li><strong>Responsabilidade:</strong> Assumimos compromissos com seriedade e cumprimos prazos estabelecidos.</li>
                        <li><strong>Transparência:</strong> Mantemos uma comunicação clara e honesta com clientes e parceiros.</li>
                        <li><strong>Sustentabilidade:</strong> Realizamos nossas atividades com respeito ao meio ambiente e às comunidades onde atuamos.</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="about-section">
                    <h4>Nossos Serviços</h4>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Manutenção Preventiva</h5>
                            <p class="card-text">Acompanhamento regular e programado de equipamentos para prevenir falhas e prolongar sua vida útil.</p>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Manutenção Corretiva</h5>
                            <p class="card-text">Diagnóstico preciso e reparo eficiente de falhas em equipamentos, minimizando o tempo de inatividade.</p>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Assistência Técnica Especializada</h5>
                            <p class="card-text">Suporte técnico de alto nível para equipamentos de diversos fabricantes e segmentos industriais.</p>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Instalação e Comissionamento</h5>
                            <p class="card-text">Instalação adequada de equipamentos seguindo as normas técnicas e recomendações dos fabricantes.</p>
                        </div>
                    </div>
                    
                    <h4 class="mt-4">Equipe Técnica</h4>
                    <p>Contamos com uma equipe de técnicos e engenheiros altamente qualificados e em constante atualização, preparados para atender às mais diversas demandas.</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <img src="https://pixabay.com/get/g10c51d50d374065a2dbb08aa54ddd8015c10333de6fd12d04480d6ff34dcefe218d9f9b4f7ec1469abe668ad06925c65b0c625b86ea2be8badb25a6364b4cd1b_1280.jpg" class="card-img-top" alt="Técnicos especializados">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Técnicos Especializados</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <img src="https://pixabay.com/get/gc26f27f26298812905a9bc1a6ff192b32c9f171e032e4899be84ad78daebf61be90c7e3505f5c2d13d58c90c8ca126776c61cf4929596da96718de2b03ea6fa9_1280.jpg" class="card-img-top" alt="Oficina de reparos">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Centro de Manutenção</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="about-section">
                    <h4 class="text-center">Nossos Equipamentos</h4>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <img src="https://pixabay.com/get/g034ef57b6da06a207b75663f1481e09cf871012bef4c09c1d700e8d6e7e6f811cc0ff6650e2a90229defd90fe642bcf861877544de912c17c7af2b09e9f5cc6a_1280.jpg" class="card-img-top" alt="Maquinário pesado">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Maquinário Pesado</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <img src="https://pixabay.com/get/g7960d46bc40b32b94c00bf993c0ec298f68d0b29833e2aa2e3ad664910ae0bc6c43d8d58dc8dd13eb0223f76becba44364fe77846a636eec0400360d0de248f6_1280.jpg" class="card-img-top" alt="Manutenção em andamento">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Manutenção em Andamento</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <img src="https://pixabay.com/get/g25baa600f77f09c7ff64abb1f9a4e38a298c39bfd6a6d2e3676d434e09b42a74db1bf4bcb9188271f553fba6253943ede7261145b791f35b76a5df14f969313d_1280.jpg" class="card-img-top" alt="Oficina de reparos">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Oficina de Reparos</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="about-section">
                    <h4 class="text-center">Sobre o Sistema SAMAPE</h4>
                    <p class="text-center mb-4">O Sistema de Gestão SAMAPE foi desenvolvido para otimizar os processos internos da empresa, proporcionando um controle eficiente de ordens de serviço, clientes, equipamentos e finanças.</p>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="card-title">Funcionalidades do Sistema</h5>
                                </div>
                                <div class="card-body">
                                    <ul>
                                        <li>Gerenciamento completo de ordens de serviço</li>
                                        <li>Cadastro e acompanhamento de clientes</li>
                                        <li>Inventário de maquinário e equipamentos</li>
                                        <li>Controle de funcionários e técnicos</li>
                                        <li>Gestão financeira de receitas e despesas</li>
                                        <li>Relatórios gerenciais e estatísticas</li>
                                        <li>Controle de acesso por perfil de usuário</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="card-title">Informações do Sistema</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Nome:</strong> Sistema de Gestão SAMAPE</p>
                                    <p><strong>Versão:</strong> <?= APP_VERSION ?></p>
                                    <p><strong>Desenvolvido por:</strong> Equipe de Tecnologia SAMAPE</p>
                                    <p><strong>Tecnologias:</strong> PHP, HTML5, CSS3, JavaScript, Bootstrap 5, SQLite</p>
                                    <p><strong>Suporte:</strong> suporte@samape.com</p>
                                    <p><strong>Licença:</strong> Uso exclusivo SAMAPE</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer text-center">
        <p><small>&copy; <?= date('Y') ?> SAMAPE - Todos os direitos reservados</small></p>
    </div>
</div>

<?php
// Include page footer
include_once 'includes/footer.php';
?>
