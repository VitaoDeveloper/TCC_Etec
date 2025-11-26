use SimpleStudy;
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,

    telefone VARCHAR(20) NULL,
    foto_perfil VARCHAR(255) NULL,

    tipo_usuario ENUM('admin', 'usuario', 'moderador') DEFAULT 'usuario',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,

    status ENUM('ativo', 'inativo', 'banido') DEFAULT 'ativo',

    -- Dados extras opcionais
    data_nascimento DATE NULL,
    genero ENUM('masculino', 'feminino', 'outro', 'nao_informar') DEFAULT 'nao_informar',
);
