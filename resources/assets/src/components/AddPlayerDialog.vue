<template>
  <modal
    id="modal-add-player"
    :title="$t('user.player.add-player')"
    :ok-button-text="$t('general.submit')"
    flex-footer
    center
    @confirm="addPlayer"
  >
    <table class="table">
      <tbody>
        <tr>
          <td v-t="'general.player.player-name'" class="key" />
          <td class="value">
            <input v-model="name" class="form-control" type="text">
          </td>
        </tr>
      </tbody>
    </table>
    <div class="callout callout-info">
      <ul class="m-0 p-0 pl-3">
        <li>{{ rule }}</li>
        <li>{{ length }}</li>
      </ul>
    </div>
  </modal>
</template>

<script>
import Modal from './Modal.vue'
import { toast } from '../scripts/notify'

export default {
  name: 'AddPlayerDialog',
  components: {
    Modal,
  },
  data() {
    return {
      name: '',
      rule: blessing.extra.rule,
      length: blessing.extra.length,
    }
  },
  methods: {
    async addPlayer() {
      const { code, message } = await this.$http.post(
        '/user/player/add',
        { name: this.name },
      )
      if (code === 0) {
        toast.success(message)
        this.$emit('add')
      } else {
        toast.error(message)
      }
    },
  },
}
</script>
