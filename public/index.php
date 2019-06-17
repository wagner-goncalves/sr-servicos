<?php
	require '../vendor/autoload.php';
	
    use \MP\App\Config;  
    
    //Recupera variáveis do ambiente 
    $dotenv = new Dotenv\Dotenv(__DIR__ . "/../private/", ".config");
    $dotenv->load();
    
    //Parâmetros de configuração do Slim
	$app = new \Slim\App(\MP\App\Config::getAppSettings());
    Config::setContainer($app->getContainer()); 

    //Middleware de validação do JWT
    $app->add(new \Slim\Middleware\JwtAuthentication([
        "secret" => getenv("JWT_KEY"),
        "path" => ["/"],
        "passthrough" => [
            "/v1/usuario/login", 
            "/v1/usuario/login-fb", 
            "/v1/usuario/email-disponivel", 
            "/v1/usuario/cadastro", 
			"/v1/usuario/cadastro-fb", 
            "/v1/usuario/termos-de-uso",
            "/v1/batch/download/imagem-politicos-mg",
            "/v1/batch/download/imagem-politicos-rs",
            "/v1/batch/camara/processa-notificacoes",
			
			"/v1/info/check-connection",

			"/v1/report/last-user",
			"/v1/report/app-use-location",
			"/v1/report/top-like",
			"/v1/report/top-dislike",

            "/v1/notificacao/enviar-agrupados",
            "/v1/notificacao/enviar-detalhada",
            "/v1/notificacao/lembrete/",
			"/v1/feed/get-comment/",
			"/v1/feed/resposta",
			
			"/v1/share/ficha/",
			"/v1/share/lembrete/",
            "/v1/cache/clear",
            "/v1/usuario/esqueci-senha",
			"/v1/usuario/valida-esqueci-senha/",
			"/v1/usuario/recuperar-senha",
			"/v1/app/check-version"
		]
    ]));
 
    $app->group("/v1", function () use ($app){
        
        //Middleware log de requisições
        $app->add(new \MP\Middleware\Log($app->getContainer()["db"]->getPdo(), Config::getLogSettings()));
        
        //Rotas
        $app->get('/partidos', 'MP\Services\Partido:getPartidos');
        $app->get('/partido/{id}', 'MP\Services\Partido:getPartido');
        $app->get('/partido/{id}/politicos', 'MP\Services\Partido:getPartidoMaisPoliticos');
        
        $app->get('/politicos', 'MP\Services\Politico:getPoliticos');
        $app->get('/politico/friends', 'MP\Services\Politico:friends');
        $app->get('/politico/friends/count', 'MP\Services\Politico:friendsCount');
        $app->get('/politico/friends/advisor', 'MP\Services\Politico:friendsAdvisor');
        $app->get('/politico/{id:[0-9]+}', 'MP\Services\Politico:getPolitico');	
		$app->get('/politico/slim/{id:[0-9]+}', 'MP\Services\Politico:getPoliticoSlim');	
		$app->get('/politico/contadores/{id:[0-9]+}', 'MP\Services\Politico:contadores');	
		$app->get('/politico/ficha/{id:[0-9]+}', 'MP\Services\Politico:getFicha');	
		$app->get('/politico/curtidas/{id:[0-9]+}', 'MP\Services\Politico:curtidas');
        $app->get('/politico/descurtidas/{id:[0-9]+}', 'MP\Services\Politico:descurtidas');
        $app->get('/politico/representantes', 'MP\Services\Politico:representantes');
		
		$app->get('/politico/rating/{id:[0-9]+}', 'MP\Services\Politico:getRating');
		$app->put('/politico/rating/{id:[0-9]+}', 'MP\Services\Politico:setRating');
        
        $app->get('/usuario/login', 'MP\Services\Usuario:login');
        $app->get('/usuario/login-fb', 'MP\Services\Usuario:loginFb');
        $app->get('/usuario/email-disponivel', 'MP\Services\Usuario:emailDisponivel');
        $app->post('/usuario/cadastro', 'MP\Services\Usuario:cadastro');	
        $app->post('/usuario/cadastro-fb', 'MP\Services\Usuario:cadastroFb');	
        $app->delete('/usuario/excluir-amizade', 'MP\Services\Usuario:excluirAmizade');
        $app->put('/usuario/adicionar-amizade', 'MP\Services\Usuario:adicionarAmizade');
        $app->delete('/usuario/delete-image', 'MP\Services\Usuario:deleteImage');	
        $app->get('/usuario/profile', 'MP\Services\Usuario:profile');
        $app->patch('/usuario/profile', 'MP\Services\Usuario:updateProfile');
        $app->patch('/usuario/device', 'MP\Services\Usuario:device');
        $app->get('/usuario/preferencias', 'MP\Services\Usuario:preferencias');
        $app->post('/usuario/foto', 'MP\Services\Usuario:foto'); 
        $app->get('/usuario/termos-de-uso', 'MP\Services\Usuario:termosDeUso');
		$app->post('/usuario/esqueci-senha', 'MP\Services\Usuario:esqueciSenha');
		$app->get('/usuario/valida-esqueci-senha/{id}', 'MP\Services\Usuario:validaEsqueciSenha');
		$app->post('/usuario/recuperar-senha', 'MP\Services\Usuario:recuperarSenha');
        
        $app->get('/feed/events', 'MP\Services\Feed:events');
		$app->get('/feed/lembrete-events', 'MP\Services\Feed:lembreteEvents');
        $app->get('/feed/featured', 'MP\Services\Feed:featured');
        $app->put('/feed/like', 'MP\Services\Feed:like');
        $app->get('/feed/stats', 'MP\Services\Feed:stats');
        $app->get('/feed/notificacao-fb/{id:[0-9]+}', 'MP\Services\Feed:getNotificacaoFb');
        $app->patch('/feed/notificacao-fb/{id:[0-9]+}/{idFb}', 'MP\Services\Feed:setIdNotificacaoFb');
		$app->put('/feed/like-presenca', 'MP\Services\Feed:likePresencaEmLote');
		$app->put('/feed/like-presenca-exclusivo', 'MP\Services\Feed:likePresencaEmLoteExclusivo');
		$app->put('/feed/like-votacao', 'MP\Services\Feed:likeVotacaoEmLote');
		$app->put('/feed/like-votacao-exclusivo', 'MP\Services\Feed:likeVotacaoEmLoteExclusivo');
		$app->get('/feed/proposicoes', 'MP\Services\Feed:proposicoes');
		$app->post('/feed/marca-proposicao', 'MP\Services\Feed:marcarProposicao');
        
        $app->put('/feed/comment', 'MP\Services\Feed:addComment');   
        $app->put('/feed/mark-as-read', 'MP\Services\Feed:markAsRead');   	
        $app->delete('/feed/comment/delete', 'MP\Services\Feed:deleteComment');      
        $app->get('/feed/comments', 'MP\Services\Feed:comments'); 
        $app->get('/feed/unread', 'MP\Services\Feed:unread'); 
		$app->put('/feed/inteiro-teor', 'MP\Services\Feed:inteiroTeor');  
		$app->get('/feed/get-comment/{id}', 'MP\Services\Feed:comment');   
		$app->post('/feed/resposta', 'MP\Services\Feed:resposta');   
    
        $app->get('/search/politicos', 'MP\Services\Search:politicos'); 
		$app->get('/search/info', 'MP\Services\Search:info'); 
        $app->get('/search/partidos', 'MP\Services\Search:partidos'); 	
        $app->post('/search/log-busca-politico', 'MP\Services\Search:logBuscaPolitico'); 	
        
        $app->get('/preferencias/instituicoes', 'MP\Services\Preferencia:instituicoes'); 
		$app->get('/preferencias/instituicoes/usuario', 'MP\Services\Preferencia:instituicoesUsuario'); 
        $app->get('/preferencias/partidos', 'MP\Services\Preferencia:partidos'); 
        $app->get('/preferencias/estados', 'MP\Services\Preferencia:estados'); 
        $app->get('/preferencias/interesses', 'MP\Services\Preferencia:interesses');
        $app->put('/preferencias/salvar', 'MP\Services\Preferencia:salvar');
		$app->get('/preferencias/politicos', 'MP\Services\Preferencia:politicos');
        
        $app->get('/batch/download/imagem-politicos-mg', 'MP\Batch\Download:imagemPoliticosMG');
        $app->get('/batch/camara/processa-notificacoes', 'MP\Batch\Camara:processaNotificacoes');

        $app->get('/batch/download/imagem-politicos-rs', 'MP\Batch\Download:imagemPoliticosRS');

        $app->post('/info/location', 'MP\Services\Info:location');
        $app->post('/info/geo-info', 'MP\Services\Info:geoInfo');
		$app->get('/info/check-connection', 'MP\Services\Info:checkConnection');
		$app->get('/info/notificacao-stats', 'MP\Services\Info:notificacaoStats');
		$app->get('/info/ideologia/{id:[0-9]+}', 'MP\Services\Info:getIdeologia');
		$app->get('/info/parametro/{chave}', 'MP\Services\Info:getParametro');
		$app->get('/info/usuario', 'MP\Services\Info:usuario');

        $app->get('/notificacao/boas-vindas', 'MP\Services\Notificacao:boasVindas'); 
        $app->get('/notificacao/enviar-detalhada', 'MP\Services\Notificacao:enviarDetalhada'); 
        $app->get('/notificacao/enviar-agrupados', 'MP\Services\Notificacao:enviarAgrupados'); 
        $app->post('/notificacao/registra-abertura', 'MP\Services\Notificacao:registraAbertura'); 	
        $app->post('/notificacao/registra-recebimento', 'MP\Services\Notificacao:registraRecebimento');
		$app->get('/notificacao/lembrete/{id}', 'MP\Services\Notificacao:lembrete'); 
		
		$app->get('/report/last-user', 'MP\Services\Report:lastUser'); 
		$app->get('/report/app-use-location', 'MP\Services\Report:appUseLocation'); 
		$app->get('/report/top-like', 'MP\Services\Report:topLike');
		$app->get('/report/top-dislike', 'MP\Services\Report:topDislike');
		$app->get('/report/gostei', 'MP\Services\Report:gostei');
        
        $app->get('/lembrete/listar', 'MP\Services\Lembrete:listar');
        $app->get('/lembrete/ultimo', 'MP\Services\Lembrete:ultimo');
		$app->get('/lembrete/{id}', 'MP\Services\Lembrete:getLembrete');
		$app->get('/lembrete/votacao/{id}', 'MP\Services\Lembrete:votacaoProposicao');
		$app->put('/lembrete/curtir-proposicao/{id:[0-9]+}', 'MP\Services\Lembrete:curtirProposicao');
		$app->post('/lembrete/cria/{id}', 'MP\Services\Lembrete:cria');
		
		$app->get('/share/ficha/{id:[0-9]+}', 'MP\Services\Share:getFicha');	
		$app->put('/share/registra-ficha', 'MP\Services\Share:registraFicha');	
		$app->put('/share/registra-app', 'MP\Services\Share:registraApp');
		$app->get('/share/lembrete/{id:[0-9]+}', 'MP\Services\Share:getLembrete');	
		$app->put('/share/registra-lembrete', 'MP\Services\Share:registraLembrete');
        
		$app->post('/ficha/log-citacao', 'MP\Services\Ficha:logCitacao');
		$app->post('/ficha/log-contas-tcu', 'MP\Services\Ficha:logContasTCU');
		
        $app->put('/cache/clear', 'MP\Services\Cache:clear');
		
		$app->get('/quiz/detalhe/{id:[0-9]+}', 'MP\Services\Quiz:getQuiz');	
		$app->get('/quiz/{id:[0-9]+}', 'MP\Services\Quiz:getQuizSlim');	
		$app->get('/quizzes', 'MP\Services\Quiz:quizzesVotacao');	
		$app->post('/quiz/responder', 'MP\Services\Quiz:responder');
		$app->post('/quiz/resultado', 'MP\Services\Quiz:salvaResultado');		
		
		$app->get('/app/check-version', 'MP\Services\App:checkVersion');  
        	
    });
	
    //Toca o barco
	$app->run();
