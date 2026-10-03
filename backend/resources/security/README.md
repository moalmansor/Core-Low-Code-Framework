# Common password list

`common-passwords.txt` is the "10k most common" list from
[SecLists](https://github.com/danielmiessler/SecLists) (MIT License,
© Daniel Miessler and contributors), lower-cased and de-duplicated. The password
policy rejects any password that appears in it. The list is bundled so that no
outbound call is made during authentication (ADR-0012).
