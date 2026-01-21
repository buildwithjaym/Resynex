from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    MYSQL_HOST: str = "127.0.0.1"
    MYSQL_PORT: int = 3306
    MYSQL_USER: str = "root"
    MYSQL_PASSWORD: str = ""
    MYSQL_DB: str = "resynex_v1"
    UPLOAD_DIR: str = "data/uploads"

    class Config:
        env_file = ".env"

settings = Settings()
