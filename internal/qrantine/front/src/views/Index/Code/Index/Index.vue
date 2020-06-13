<template>
    <full-layout>
        <card>
            <f-header text="Code" />
            <div class="canvas mb-3 mt-1">
                <canvas id="code" width="512" height="128">
                    Bruh
                </canvas>
            </div>
            <f-header text="Verify" />
            <form class="mt-3" @submit.prevent="njskd12v">
                <div class="ff">
                    <f-input
                        type="text"
                        name="home"
                        v-model="home"
                        :errors="errors['home']"
                        placeholder="Home"
                    />
                </div>
                <div class="ff">
                    <f-input
                        type="text"
                        name="work"
                        v-model="work"
                        :errors="errors['work']"
                        placeholder="Work"
                    />
                </div>
                <div class="ff">
                    <f-detail :errors="[errors['err']]" />
                </div>
                <div class="ff">
                    <input type="submit" value="Verify" class="btn" />
                </div>
            </form>
            <div v-if="!$types.isNull(result)" class="mt-1">
                <div class="ok" v-if="result" />
                <div class="nok" v-else />
            </div>
        </card>
    </full-layout>
</template>

<script>
import FInput from '@/components/Form/Input';
import FHeader from '@/components/Form/Header';

export default {
    components: {
        FInput,
        FHeader,
    },

    data: function() {
        return {
            code: null,
            home: null,
            work: null,
            result: null,
            errors: {},
        };
    },

    methods: {
        fetchCode: async function() {
            try {
                this.code = (
                    await this.$http.get(`/code/${this.$route.params.id}/`)
                ).data.ok;

                const canvas = document.getElementById('code');
                const ctx = canvas.getContext('2d');
                for (let i = 0; i < 512; i += 1) {
                    if (this.code[i] === 0) {
                        ctx.fillStyle = 'rgb(0, 0, 0)';
                        ctx.fillRect(i, 1, 1, 127);
                    } else {
                        ctx.fillStyle = 'rgb(255, 255, 255)';
                        ctx.fillRect(i, 1, 1, 127);
                    }
                }

                for (let i = 0; i < 32; ++i) {
                    if (this.code[512 + i] === 0) {
                        ctx.fillStyle = 'rgb(0, 0, 0)';
                        ctx.fillRect(i * 16, 0, 16, 1);
                        ctx.fillRect(i * 16, 127, 16, 1);
                    } else {
                        ctx.fillStyle = 'rgb(255, 255, 255)';
                        ctx.fillRect(i * 16, 0, 16, 1);
                        ctx.fillRect(i * 16, 127, 16, 1);
                    }
                }
            } catch (error) {
                this.errors = error.response.data;
            }
        },

        njskd12v: async function() {
            if (
                this.code.length !== 544 ||
                this.$types.isNull(this.home) ||
                this.$types.isNull(this.work)
            ) {
                this.result = false;
                return;
            }

            let hh = this.code.slice(0, 256);
            let hhh = [];
            for (let i = 0; i < hh.length; i += 8) {
                hhh.push(
                    parseInt(
                        hh
                            .slice(i, i + 8)
                            .map(x => x.toString())
                            .join(''),
                        2
                    )
                );
            }

            let ww = this.code.slice(256, 512);
            let www = [];
            for (let i = 0; i < ww.length; i += 8) {
                www.push(
                    parseInt(
                        ww
                            .slice(i, i + 8)
                            .map(x => x.toString())
                            .join(''),
                        2
                    )
                );
            }

            let it = this.code.slice(512);
            let itt = [];
            for (let i = 0; i < it.length; i += 8) {
                itt.push(
                    parseInt(
                        it
                            .slice(i, i + 8)
                            .map(x => x.toString())
                            .join(''),
                        2
                    )
                );
            }

            let r1 = 0;
            for (let i = 0; i < 32; i += 1) {
                r1 ^= hhh[i] ^ this.home.charCodeAt(i);
            }

            if (r1 !== 0) {
                this.result = false;
                return;
            }

            let r2 = 0;
            for (let i = 0; i < 32; i += 1) {
                r2 ^= www[i] ^ this.work.charCodeAt(i);
            }

            if (r2 !== 0) {
                this.result = false;
                return;
            }

            let con = this.work + this.home;
            for (let i = 0; i < 4; i += 1) {
                let r = 0;
                for (let j = i; j < con.length; j += 4) {
                    r ^= con.charCodeAt(j);
                }
                if (r !== itt[i]) {
                    this.result = false;
                    return;
                }
            }

            this.result = true;
        },
    },

    created: async function() {
        await this.fetchCode();
    },

    watch: {
        $route: async function() {
            await this.fetchCode();
        },
    },
};
</script>

<style lang="scss" scoped>
.canvas {
    display: flex;
    flex-flow: row nowrap;
    justify-content: center;
}

.ok {
    @include use-theme {
        background-color: $green;
        height: 2em;
    }
}

.nok {
    @include use-theme {
        background-color: $red;
        height: 2em;
    }
}
</style>
