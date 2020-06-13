<template>
    <full-layout>
        <card>
            <f-header text="Create" />
            <form class="mt-3" @submit.prevent="njskd12">
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
                    <input type="submit" value="Create" class="btn" />
                </div>
            </form>
        </card>
    </full-layout>
</template>

<script>
import FInput from '@/components/Form/Input';
import FHeader from '@/components/Form/Header';
import { mapState } from 'vuex';

export default {
    components: {
        FInput,
        FHeader,
    },

    data: function() {
        return {
            work: null,
            errors: {},
        };
    },

    methods: {
        njskd12: async function() {
            let home = this.user.home.slice(0, 32).padStart(32, '0');
            let work = this.work.slice(0, 32).padStart(32, '0');

            let con = home + work;

            let seed = [];
            let cnt = 0;
            const arr = new Uint8Array(1);
            for (const i of con) {
                if (cnt % 2 === 0) {
                    window.crypto.getRandomValues(arr);
                }
                cnt += 1;
                let bn = (i.charCodeAt(0) ^ arr[0]).toString(2).padStart(8, '0');
                for (let j of bn) {
                    seed.push(parseInt(j, 10));
                }
            }

            for (let i = 0; i < 4; i += 1) {
                let res = 0;
                for (let j = i; j < con.length; j += 4) {
                    res ^= con.charCodeAt(j);
                }
                let bn = res.toString(2).padStart(8, '0');
                for (let j of bn) {
                    seed.push(parseInt(j, 10));
                }
            }

            try {
                const cid = (
                    await this.$http.post('/code/', {
                        work: this.work,
                        code: seed,
                    })
                ).data.ok;

                this.$router.push({ name: 'code_index', params: { id: cid } });
            } catch (error) {
                this.errors = error.response.data;
            }
        },
    },

    computed: mapState(['user']),
};
</script>
