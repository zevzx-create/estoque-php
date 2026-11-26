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

ALTER TABLE categorias
    ADD COLUMN usuario_id INT NULL,
    DROP INDEX nome,
    ADD UNIQUE KEY uq_categorias_nome (usuario_id,nome),
    ADD UNIQUE KEY uq_categorias_id (usuario_id,id),
    ADD CONSTRAINT fk_categorias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id);

ALTER TABLE fornecedores
    ADD COLUMN usuario_id INT NULL,
    DROP INDEX nome,
    ADD UNIQUE KEY uq_fornecedores_nome (usuario_id,nome),
    ADD UNIQUE KEY uq_fornecedores_id (usuario_id,id),
    ADD CONSTRAINT fk_fornecedores_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id);

ALTER TABLE locais
    ADD COLUMN usuario_id INT NULL,
    DROP INDEX nome,
    ADD UNIQUE KEY uq_locais_nome (usuario_id,nome),
    ADD UNIQUE KEY uq_locais_id (usuario_id,id),
    ADD CONSTRAINT fk_locais_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id);

ALTER TABLE produtos
    ADD COLUMN usuario_id INT NULL,
    ADD CONSTRAINT fk_produtos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    ADD CONSTRAINT fk_produtos_categoria_usuario FOREIGN KEY (usuario_id,categoria_id) REFERENCES categorias(usuario_id,id),
    ADD CONSTRAINT fk_produtos_fornecedor_usuario FOREIGN KEY (usuario_id,fornecedor_id) REFERENCES fornecedores(usuario_id,id),
    ADD CONSTRAINT fk_produtos_local_usuario FOREIGN KEY (usuario_id,local_id) REFERENCES locais(usuario_id,id);

ALTER TABLE historico
    ADD COLUMN usuario_id INT NULL,
    ADD INDEX idx_historico_usuario (usuario_id,id),
    ADD CONSTRAINT fk_historico_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id);
