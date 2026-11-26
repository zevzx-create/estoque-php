CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    legado TINYINT NULL UNIQUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tentativas_login (
    chave CHAR(64) NOT NULL,
    janela INT UNSIGNED NOT NULL,
    tentativas INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (chave, janela),
    INDEX (janela)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    UNIQUE KEY uq_categorias_nome (usuario_id,nome),
    UNIQUE KEY uq_categorias_id (usuario_id,id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    UNIQUE KEY uq_fornecedores_nome (usuario_id,nome),
    UNIQUE KEY uq_fornecedores_id (usuario_id,id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE locais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    UNIQUE KEY uq_locais_nome (usuario_id,nome),
    UNIQUE KEY uq_locais_id (usuario_id,id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    preco DECIMAL(10,2) NOT NULL,
    quantidade INT UNSIGNED NOT NULL,
    categoria_id INT NOT NULL,
    fornecedor_id INT NOT NULL,
    local_id INT NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (usuario_id,categoria_id) REFERENCES categorias(usuario_id,id),
    FOREIGN KEY (usuario_id,fornecedor_id) REFERENCES fornecedores(usuario_id,id),
    FOREIGN KEY (usuario_id,local_id) REFERENCES locais(usuario_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tabela VARCHAR(40) NOT NULL,
    registro_id INT NOT NULL,
    operacao VARCHAR(20) NOT NULL,
    antes LONGTEXT NULL,
    depois LONGTEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX (usuario_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
