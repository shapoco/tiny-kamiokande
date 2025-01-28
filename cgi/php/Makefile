.PHONY: test setup launch_server

TEST_PORT := 9996
TEST_URL := http://localhost:$(TEST_PORT)/tiny-kamiokande/api.php?fmt=bin

TMP_BIN = tmp.bin

test:
	wget -O $(TMP_BIN) $(TEST_URL)
	od -Ax -tx1z $(TMP_BIN)

setup:
	sudo apt install php8.3 php8.3-gd

launch_server:
	@echo "$(TEST_URL)"
	php -S "localhost:$(TEST_PORT)" -t www

