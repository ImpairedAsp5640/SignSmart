document.addEventListener("DOMContentLoaded", () => {
  const currentPage = document.querySelector(".nav-item.active")

  if (currentPage) {
    const pageId = currentPage.getAttribute("href").split("=")[1]

    switch (pageId) {
      case "chat":
        initChatPage()
        break
      case "contracts":
        initContractsPage()
        break
      case "comparisons":
        initComparisonsPage()
        break
    }
  }

  initModals()

  const newChatBtn = document.querySelector(".options .new-chat")
  if (newChatBtn) {
    newChatBtn.addEventListener("click", () => {
      console.log("New Chat button clicked")
      fetch("api.php?action=new_chat", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
      })
        .then((response) => response.json())
        .then((data) => {
          console.log("New chat response:", data)
          if (data.success) {
            window.location.href = "index.php?page=chat&chat_id=" + data.chat_id
          } else {
            console.error("Error creating new chat:", data.error)
            alert("Error creating new chat: " + data.error)
          }
        })
        .catch((error) => {
          console.error("Error creating new chat:", error)
          alert("Error creating new chat. Please try again.")
        })
    })
  }

  const uploadContractBtn = document.querySelector(".options .upload-contract")
  if (uploadContractBtn) {
    uploadContractBtn.addEventListener("click", () => {
      console.log("Upload Contract button clicked")
      window.location.href = "index.php?page=contracts&action=upload"
    })
  }
})

function initChatPage() {
  const messageInput = document.getElementById("message-input")
  const sendButton = document.getElementById("send-button")
  const fileUpload = document.getElementById("file-upload")
  const filePreviewContainer = document.getElementById("file-preview-container")

  if (!messageInput || !sendButton) return

  messageInput.addEventListener("input", function () {
    sendButton.disabled = messageInput.value.trim() === "" && !fileUpload.files.length

    this.style.height = "auto"
    this.style.height = this.scrollHeight + "px"
  })

  if (fileUpload) {
    fileUpload.addEventListener("change", (e) => {
      if (e.target.files.length > 0) {
        const file = e.target.files[0]

        if (file.size > 50 * 1024 * 1024) {
          alert("File size exceeds the 50MB limit. Please choose a smaller file.")
          fileUpload.value = ""
          return
        }

        filePreviewContainer.innerHTML = ""
        filePreviewContainer.style.display = "block"

        const filePreview = document.createElement("div")
        filePreview.className = "file-preview"

        if (file.type.startsWith("image/")) {
          const img = document.createElement("img")
          img.src = URL.createObjectURL(file)
          filePreview.appendChild(img)
        } else {
          const fileIcon = document.createElement("i")
          fileIcon.className = "fas fa-file"
          filePreview.appendChild(fileIcon)
        }

        const fileInfo = document.createElement("div")
        fileInfo.className = "file-info"

        const fileName = document.createElement("div")
        fileName.className = "file-name"
        fileName.textContent = file.name

        const fileSize = document.createElement("div")
        fileSize.className = "file-size"
        fileSize.textContent = formatFileSize(file.size)

        fileInfo.appendChild(fileName)
        fileInfo.appendChild(fileSize)

        const removeButton = document.createElement("button")
        removeButton.className = "remove-file"
        removeButton.innerHTML = '<i class="fas fa-times"></i>'
        removeButton.addEventListener("click", () => {
          filePreviewContainer.style.display = "none"
          fileUpload.value = ""
          sendButton.disabled = messageInput.value.trim() === ""
        })

        filePreview.appendChild(fileInfo)
        filePreview.appendChild(removeButton)

        filePreviewContainer.appendChild(filePreview)

        sendButton.disabled = false
      }
    })
  }

  document.querySelectorAll(".example-prompt").forEach((button) => {
    button.addEventListener("click", function () {
      messageInput.value = this.getAttribute("data-prompt")
      sendButton.disabled = false
      messageInput.focus()

      messageInput.style.height = "auto"
      messageInput.style.height = messageInput.scrollHeight + "px"
    })
  })

  sendButton.addEventListener("click", () => {
    sendMessage()
  })

  messageInput.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault()
      if (!sendButton.disabled) {
        sendMessage()
      }
    }
  })

  loadChatHistory()

  const newChatBtn = document.getElementById("new-chat-btn")
  if (newChatBtn) {
    newChatBtn.addEventListener("click", () => {
      fetch("api.php?action=new_chat", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            document.getElementById("messages").innerHTML = ""
            document.getElementById("welcome-message").style.display = "block"
            window.currentChatId = data.chat_id

            loadChatHistory()

            window.location.href = "index.php?page=chat&chat_id=" + data.chat_id
          } else {
            console.error("Error creating new chat:", data.error)
            alert("Error creating new chat: " + data.error)
          }
        })
        .catch((error) => {
          console.error("Error creating new chat:", error)
          alert("Error creating new chat. Please try again.")
        })
    })
  }
}

function sendMessage() {
  const messageInput = document.getElementById("message-input")
  const fileUpload = document.getElementById("file-upload")
  const welcomeMessage = document.getElementById("welcome-message")
  const messagesContainer = document.getElementById("messages")
  const chatContainer = document.getElementById("chat-container")
  const filePreviewContainer = document.getElementById("file-preview-container")
  const sendButton = document.getElementById("send-button")

  const message = messageInput.value.trim()
  const selectedFile = fileUpload.files.length > 0 ? fileUpload.files[0] : null

  const formData = new FormData()
  formData.append("message", message)

  if (selectedFile) {
    formData.append("file", selectedFile)
  }

  if (window.currentChatId) {
    formData.append("chat_id", window.currentChatId)
  }

  if (welcomeMessage.style.display !== "none") {
    welcomeMessage.style.display = "none"
  }

  appendMessage("user", message, selectedFile ? URL.createObjectURL(selectedFile) : null)

  messageInput.value = ""
  messageInput.style.height = "auto"
  fileUpload.value = ""
  filePreviewContainer.style.display = "none"
  sendButton.disabled = true

  const typingIndicator = document.createElement("div")
  typingIndicator.className = "message assistant-message typing-indicator"
  typingIndicator.innerHTML = `
      <div class="typing-dots">
          <span></span>
          <span></span>
          <span></span>
      </div>
  `
  messagesContainer.appendChild(typingIndicator)

  chatContainer.scrollTop = chatContainer.scrollHeight

  fetch("api.php?action=send_message", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`)
      }
      return response.json()
    })
    .then((data) => {
      if (messagesContainer.contains(typingIndicator)) {
        messagesContainer.removeChild(typingIndicator)
      }

      if (data.success) {
        appendMessage("assistant", data.response, data.file_path)

        if (data.update_history) {
          loadChatHistory()
        }

        if (data.chat_id) {
          window.currentChatId = data.chat_id
        }
      } else {
        const errorMsg = data.error || "An unknown error occurred"
        appendMessage("assistant", `<div class="error-message">Sorry, an error occurred: ${errorMsg}</div>`)
        console.error("API Error:", errorMsg)
      }

      chatContainer.scrollTop = chatContainer.scrollHeight
    })
    .catch((error) => {
      console.error("Error sending message:", error)

      if (messagesContainer.contains(typingIndicator)) {
        messagesContainer.removeChild(typingIndicator)
      }

      const errorMessage = document.createElement("div")
      errorMessage.className = "message assistant-message"
      errorMessage.innerHTML = `
      <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <p>Sorry, an error occurred while sending your message: ${error.message}</p>
        <button class="retry-button"><i class="fas fa-redo"></i> Retry</button>
      </div>
    `
      messagesContainer.appendChild(errorMessage)

      const retryButton = errorMessage.querySelector(".retry-button")
      if (retryButton) {
        retryButton.addEventListener("click", () => {
          messagesContainer.removeChild(errorMessage)
          sendMessage()
        })
      }

      chatContainer.scrollTop = chatContainer.scrollHeight
    })
}

function appendMessage(sender, message, filePath) {
  const messagesContainer = document.getElementById("messages")

  const messageDiv = document.createElement("div")
  messageDiv.classList.add("message")
  messageDiv.classList.add(sender === "user" ? "user-message" : "assistant-message")

  const messageContent = document.createElement("div")
  messageContent.classList.add("message-content")

  if (typeof message === "string" && message.includes('<div class="error-message">')) {
    messageContent.innerHTML = message
  } else {
    messageContent.textContent = message
  }

  messageDiv.appendChild(messageContent)

  if (filePath) {
    if (filePath.startsWith("blob:")) {
      const filePreview = document.createElement("div")
      filePreview.className = "file-preview-in-message"

      const img = document.createElement("img")
      img.src = filePath
      img.className = "message-image"
      img.alt = "Uploaded image"

      filePreview.appendChild(img)
      messageDiv.appendChild(filePreview)
    } else {
      const fileExtension = filePath.split(".").pop().toLowerCase()
      const isImage = ["jpg", "jpeg", "png", "gif"].includes(fileExtension)

      if (isImage) {
        const img = document.createElement("img")
        img.src = filePath
        img.className = "message-image"
        img.alt = "Uploaded image"
        messageDiv.appendChild(img)
      } else {
        const fileLink = document.createElement("a")
        fileLink.href = filePath
        fileLink.className = "file-link"
        fileLink.innerHTML = '<i class="fas fa-file"></i> View File'
        fileLink.target = "_blank"
        messageDiv.appendChild(fileLink)
      }
    }
  }

  messagesContainer.appendChild(messageDiv)

  const chatContainer = document.getElementById("chat-container")
  chatContainer.scrollTop = chatContainer.scrollHeight
}

function loadChatHistory() {
  fetch("api.php?action=get_chat_history")
    .then((response) => response.json())
    .then((chats) => {
      const chatHistory = document.getElementById("chat-history")
      if (chatHistory) {
        chatHistory.innerHTML = ""

        if (chats.length === 0) {
          chatHistory.innerHTML = '<div class="empty-history">No chat history yet</div>'
          return
        }

        chats.forEach((chat) => {
          const chatItem = document.createElement("div")
          chatItem.className = "chat-history-item"
          if (window.currentChatId && window.currentChatId == chat.id) {
            chatItem.classList.add("active")
          }

          const chatTitle = document.createElement("div")
          chatTitle.className = "chat-history-title"
          chatTitle.textContent = chat.title

          const chatDate = document.createElement("div")
          chatDate.className = "chat-history-date"
          chatDate.textContent = formatDate(chat.created_at)

          chatItem.appendChild(chatTitle)
          chatItem.appendChild(chatDate)
          chatItem.dataset.id = chat.id

          chatItem.addEventListener("click", function () {
            loadChat(chat.id)

            document.querySelectorAll(".chat-history-item").forEach((item) => {
              item.classList.remove("active")
            })
            this.classList.add("active")
          })

          chatHistory.appendChild(chatItem)
        })
      }
    })
    .catch((error) => console.error("Error loading chat history:", error))
}

function loadChat(chatId) {
  fetch(`api.php?action=get_chat&chat_id=${chatId}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success === false) {
        console.error("Error loading chat:", data.error)
        return
      }

      const messagesContainer = document.getElementById("messages")
      const welcomeMessage = document.getElementById("welcome-message")

      messagesContainer.innerHTML = ""
      welcomeMessage.style.display = "none"

      window.currentChatId = chatId

      data.messages.forEach((message) => {
        appendMessage(message.role, message.content, message.file_path)
      })

      const chatContainer = document.getElementById("chat-container")
      chatContainer.scrollTop = chatContainer.scrollHeight
    })
    .catch((error) => console.error("Error loading chat:", error))
}

function initContractsPage() {
  const urlParams = new URLSearchParams(window.location.search)
  if (urlParams.get("action") === "upload") {
    const uploadModal = document.getElementById("upload-contract-modal")
    if (uploadModal) {
      uploadModal.style.display = "block"
    }
  }

  document.querySelectorAll(".filter-btn").forEach((button) => {
    button.addEventListener("click", function () {
      document.querySelectorAll(".filter-btn").forEach((btn) => {
        btn.classList.remove("active")
      })

      this.classList.add("active")

      const filter = this.getAttribute("data-filter")

      document.querySelectorAll(".contract-card").forEach((card) => {
        if (filter === "all" || card.getAttribute("data-status") === filter) {
          card.style.display = "block"
        } else {
          card.style.display = "none"
        }
      })
    })
  })

  const searchInput = document.getElementById("contract-search")
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      const searchTerm = this.value.toLowerCase()

      document.querySelectorAll(".contract-card").forEach((card) => {
        const title = card.querySelector("h3").textContent.toLowerCase()

        if (title.includes(searchTerm)) {
          card.style.display = "block"
        } else {
          card.style.display = "none"
        }
      })
    })
  }

  document.querySelectorAll(".view-contract-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const contractId = this.getAttribute("data-id")
      viewContract(contractId)
    })
  })

  document.querySelectorAll(".analyze-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const contractId = this.getAttribute("data-id")
      analyzeContract(contractId, this)
    })
  })

  document.querySelectorAll(".sign-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const contractId = this.getAttribute("data-id")
      updateContractStatus(contractId, "signed")
    })
  })

  document.querySelectorAll(".reject-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const contractId = this.getAttribute("data-id")
      updateContractStatus(contractId, "rejected")
    })
  })

  const uploadFirstContractBtn = document.getElementById("upload-first-contract-btn")
  if (uploadFirstContractBtn) {
    uploadFirstContractBtn.addEventListener("click", () => {
      document.getElementById("upload-contract-modal").style.display = "block"
    })
  }
}

function initComparisonsPage() {
  document.querySelectorAll(".view-comparison-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const comparisonId = this.getAttribute("data-id")
      viewComparison(comparisonId)
    })
  })

  const newComparisonBtn = document.getElementById("new-comparison-btn")
  if (newComparisonBtn) {
    newComparisonBtn.addEventListener("click", () => {
      document.getElementById("new-comparison-modal").style.display = "block"
    })
  }

  const createFirstComparisonBtn = document.getElementById("create-first-comparison-btn")
  if (createFirstComparisonBtn) {
    createFirstComparisonBtn.addEventListener("click", () => {
      document.getElementById("new-comparison-modal").style.display = "block"
    })
  }

  const newComparisonForm = document.getElementById("new-comparison-form")
  if (newComparisonForm) {
    newComparisonForm.addEventListener("submit", function (e) {
      e.preventDefault()

      const selectedContracts = []
      document.querySelectorAll('input[name="contracts[]"]:checked').forEach((checkbox) => {
        selectedContracts.push(checkbox.value)
      })

      if (selectedContracts.length < 2 || selectedContracts.length > 3) {
        document.getElementById("contract-selection-error").style.display = "block"
        return
      }

      document.getElementById("contract-selection-error").style.display = "none"

      const formData = new FormData(this)

      const formActions = this.querySelector(".form-actions")
      formActions.innerHTML = `
              <div class="loading-indicator">
                  <div class="spinner"></div>
              </div>
          `

      fetch("contract_api.php?action=create_comparison", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            window.location.reload()
          } else {
            alert("Error creating comparison: " + data.error)

            formActions.innerHTML = `
                      <button type="submit" class="primary-btn">Create Comparison</button>
                      <button type="button" class="secondary-btn" id="cancel-comparison">Cancel</button>
                  `

            document.getElementById("cancel-comparison").addEventListener("click", () => {
              document.getElementById("new-comparison-modal").style.display = "none"
            })
          }
        })
        .catch((error) => {
          console.error("Error creating comparison:", error)
          alert("An error occurred while creating the comparison. Please try again.")

          formActions.innerHTML = `
                  <button type="submit" class="primary-btn">Create Comparison</button>
                  <button type="button" class="secondary-btn" id="cancel-comparison">Cancel</button>
              `

          document.getElementById("cancel-comparison").addEventListener("click", () => {
            document.getElementById("new-comparison-modal").style.display = "none"
          })
        })
    })
  }
}

function initModals() {
  document.querySelectorAll(".modal .close").forEach((closeBtn) => {
    closeBtn.addEventListener("click", function () {
      this.closest(".modal").style.display = "none"
    })
  })

  window.addEventListener("click", (e) => {
    if (e.target.classList.contains("modal")) {
      e.target.style.display = "none"
    }
  })

  const uploadContractBtn = document.getElementById("upload-contract-btn")
  if (uploadContractBtn) {
    uploadContractBtn.addEventListener("click", () => {
      document.getElementById("upload-contract-modal").style.display = "block"
    })
  }

  document.querySelectorAll(".upload-tab").forEach((tab) => {
    tab.addEventListener("click", function () {
      document.querySelectorAll(".upload-tab").forEach((t) => {
        t.classList.remove("active")
      })

      this.classList.add("active")

      document.querySelectorAll(".upload-tab-content").forEach((content) => {
        content.style.display = "none"
      })

      const tabId = this.getAttribute("data-tab")
      document.getElementById(tabId + "-upload-tab").style.display = "block"
    })
  })

  const contractFile = document.getElementById("contract-file")
  if (contractFile) {
    contractFile.addEventListener("change", function () {
      const fileName = this.files.length > 0 ? this.files[0].name : "No file chosen"
      document.getElementById("selected-file-name").textContent = fileName
    })
  }

  const contractUploadForm = document.getElementById("contract-upload-form")
  if (contractUploadForm) {
    contractUploadForm.addEventListener("submit", function (e) {
      e.preventDefault()

      const title = document.getElementById("contract-title").value.trim()
      if (!title) {
        alert("Please enter a contract title")
        return
      }

      const activeTab = document.querySelector(".upload-tab.active").getAttribute("data-tab")
      if (activeTab === "file") {
        const file = document.getElementById("contract-file").files[0]
        if (!file) {
          alert("Please select a file to upload")
          return
        }
      } else if (activeTab === "link") {
        const link = document.getElementById("contract-link").value.trim()
        if (!link) {
          alert("Please enter a URL to the contract")
          return
        }
      }

      const formData = new FormData(this)

      const formActions = this.querySelector(".form-actions")
      formActions.innerHTML = `
              <div class="loading-indicator">
                  <div class="spinner"></div>
              </div>
          `

      console.log("Submitting contract form with data:")
      for (const pair of formData.entries()) {
        console.log(pair[0] + ": " + pair[1])
      }

      fetch("upload_contract.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`)
          }
          return response.json()
        })
        .then((data) => {
          if (data.success) {
            window.location.reload()
          } else {
            alert("Error uploading contract: " + data.error)

            formActions.innerHTML = `
                      <button type="submit" class="primary-btn">Upload Contract</button>
                      <button type="button" class="secondary-btn" id="cancel-upload">Cancel</button>
                  `

            document.getElementById("cancel-upload").addEventListener("click", () => {
              document.getElementById("upload-contract-modal").style.display = "none"
            })
          }
        })
        .catch((error) => {
          console.error("Error uploading contract:", error)
          alert("An error occurred while uploading the contract. Please try again.")

          formActions.innerHTML = `
                  <button type="submit" class="primary-btn">Upload Contract</button>
                  <button type="button" class="secondary-btn" id="cancel-upload">Cancel</button>
              `

          document.getElementById("cancel-upload").addEventListener("click", () => {
            document.getElementById("upload-contract-modal").style.display = "none"
          })
        })
    })
  }

  const cancelUploadBtn = document.getElementById("cancel-upload")
  if (cancelUploadBtn) {
    cancelUploadBtn.addEventListener("click", () => {
      document.getElementById("upload-contract-modal").style.display = "none"
    })
  }

  const cancelComparisonBtn = document.getElementById("cancel-comparison")
  if (cancelComparisonBtn) {
    cancelComparisonBtn.addEventListener("click", () => {
      document.getElementById("new-comparison-modal").style.display = "none"
    })
  }
}

function viewContract(contractId) {
  const modal = document.getElementById("contract-details-modal")
  const container = document.getElementById("contract-details-container")

  container.innerHTML = `
      <div class="loading-indicator">
          <div class="spinner"></div>
          <p>Loading contract details...</p>
      </div>
  `

  modal.style.display = "block"

  fetch(`contract_api.php?action=get_contract&contract_id=${contractId}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`)
      }
      return response.json()
    })
    .then((data) => {
      if (data.success) {
        const contract = data.contract

        let html = `
                  <div class="contract-detail">
                      <div class="contract-detail-header">
                          <div class="contract-title">
                              <h2>${contract.title}</h2>
                              <div class="contract-metadata">
                                  <div><i class="fas fa-calendar-alt"></i> ${formatDate(contract.created_at)}</div>
                                  <div><i class="fas fa-tag"></i> Status: <span class="contract-status ${contract.status}">${contract.status.charAt(0).toUpperCase() + contract.status.slice(1)}</span></div>
                              </div>
                          </div>
                          <div class="contract-actions">
              `

        if (contract.status === "pending") {
          html += `<button class="analyze-btn" data-id="${contract.id}"><i class="fas fa-robot"></i> Analyze</button>`
        } else if (contract.status === "analyzed") {
          html += `
                      <button class="sign-btn" data-id="${contract.id}"><i class="fas fa-signature"></i> Sign</button>
                      <button class="reject-btn" data-id="${contract.id}"><i class="fas fa-times"></i> Reject</button>
                  `
        }

        html += `
                          </div>
                      </div>
              `

        html += `
                  <div class="contract-document">
              `

        if (contract.file_type.startsWith("image/")) {
          html += `<img src="${contract.file_path}" alt="${contract.title}" class="contract-image">`
        } else if (contract.file_type === "application/pdf") {
          html += `<iframe src="${contract.file_path}" class="contract-pdf"></iframe>`
        } else if (contract.file_content) {
          html += `<pre class="contract-text">${contract.file_content}</pre>`
        } else {
          html += `<p>This document cannot be previewed directly. <a href="${contract.file_path}" target="_blank" class="download-link"><i class="fas fa-download"></i> Download</a> to view.</p>`
        }

        html += `
                  </div>
              `

        if (contract.analysis) {
          fetch("display_contract.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `action=format_analysis&analysis=${encodeURIComponent(JSON.stringify(contract.analysis))}`,
          })
            .then((response) => response.text())
            .then((formattedAnalysis) => {
              const analysisContainer = document.createElement("div")
              analysisContainer.className = "contract-analysis"
              analysisContainer.innerHTML = `
                          <h3 class="analysis-title"><i class="fas fa-chart-bar"></i> Contract Analysis</h3>
                          ${formattedAnalysis}
                      `

              document.querySelector(".contract-detail").appendChild(analysisContainer)

              attachContractButtonListeners()
            })
            .catch((error) => {
              console.error("Error formatting analysis:", error)
              html += `
                          <div class="contract-analysis">
                              <h3 class="analysis-title"><i class="fas fa-chart-bar"></i> Contract Analysis</h3>
                              <div class="analysis-section">
                                  <h4><i class="fas fa-file-alt"></i> Summary</h4>
                                  <div class="analysis-content">
                                      <p>${contract.analysis.summary}</p>
                                  </div>
                              </div>
                              
                              <div class="final-recommendation">
                                  <div class="recommendation-header caution-recommendation">
                                      <i class="fas fa-exclamation-circle"></i>
                                      <h4>Recommendation</h4>
                                  </div>
                                  <p>${contract.analysis.recommendation}</p>
                              </div>
                          </div>
                      `

              container.innerHTML = html

              attachContractButtonListeners()
            })
        } else {
          html += `</div>`

          container.innerHTML = html

          attachContractButtonListeners()
        }

        container.innerHTML = html
      } else {
        container.innerHTML = `<div class="error-message">Error loading contract: ${data.error}</div>`
      }
    })
    .catch((error) => {
      console.error("Error loading contract:", error)
      container.innerHTML = `<div class="error-message">An error occurred while loading the contract. Please try again.</div>`
    })
}

function displayContractAnalysis(contractId) {
  const modal = document.getElementById("contract-details-modal")
  const container = document.getElementById("contract-details-container")

  container.innerHTML = `
      <div class="loading-indicator">
          <div class="spinner"></div>
          <p>Loading contract details and analyzing content...</p>
      </div>
  `

  modal.style.display = "block"

  fetch(`contract_api.php?action=get_contract&contract_id=${contractId}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`)
      }
      return response.json()
    })
    .then((data) => {
      if (data.success) {
        const contract = data.contract

        let html = `
                  <div class="contract-detail">
                      <div class="contract-detail-header">
                          <div class="contract-title">
                              <h2>${contract.title}</h2>
                              <div class="contract-metadata">
                                  <div><i class="fas fa-calendar-alt"></i> ${formatDate(contract.created_at)}</div>
                                  <div><i class="fas fa-tag"></i> Status: <span class="contract-status ${contract.status}">${contract.status.charAt(0).toUpperCase() + contract.status.slice(1)}</span></div>
                              </div>
                          </div>
                          <div class="contract-actions">
              `

        if (contract.status === "pending") {
          html += `<button class="analyze-btn" data-id="${contract.id}"><i class="fas fa-robot"></i> Analyze</button>`
        } else if (contract.status === "analyzed") {
          html += `
                      <button class="sign-btn" data-id="${contract.id}"><i class="fas fa-signature"></i> Sign</button>
                      <button class="reject-btn" data-id="${contract.id}"><i class="fas fa-times"></i> Reject</button>
                  `
        }

        html += `
                          </div>
                      </div>
              `

        html += `
                  <div class="contract-document">
              `

        if (contract.file_type && contract.file_type.startsWith("image/")) {
          html += `<img src="${contract.file_path}" alt="${contract.title}" class="contract-image">`
        } else if (contract.file_type === "application/pdf") {
          html += `<iframe src="${contract.file_path}" class="contract-pdf"></iframe>`
        } else if (contract.file_content) {
          html += `<pre class="contract-text">${contract.file_content}</pre>`
        } else {
          html += `<p>This document cannot be previewed directly. <a href="${contract.file_path}" target="_blank" class="download-link"><i class="fas fa-download"></i> Download</a> to view.</p>`
        }

        html += `
                  </div>
              `

        if (contract.analysis) {
          fetch("display_contract.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `action=format_analysis&analysis=${encodeURIComponent(JSON.stringify(contract.analysis))}`,
          })
            .then((response) => response.text())
            .then((formattedAnalysis) => {
              const analysisContainer = document.createElement("div")
              analysisContainer.className = "contract-analysis"
              analysisContainer.innerHTML = `
                          <h3 class="analysis-title"><i class="fas fa-chart-bar"></i> Contract Analysis</h3>
                          ${formattedAnalysis}
                      `

              document.querySelector(".contract-detail").appendChild(analysisContainer)

              attachContractButtonListeners()
            })
            .catch((error) => {
              console.error("Error formatting analysis:", error)
              const analysisContainer = document.createElement("div")
              analysisContainer.className = "contract-analysis"

              let analysisHtml = `
                <h3 class="analysis-title"><i class="fas fa-chart-bar"></i> Contract Analysis</h3>
              `

              if (contract.analysis.summary) {
                analysisHtml += `
                  <div class="analysis-section">
                    <h4><i class="fas fa-file-alt"></i> Summary</h4>
                    <div class="analysis-content">
                      <p>${contract.analysis.summary}</p>
                    </div>
                  </div>
                `
              }

              if (contract.analysis.recommendation) {
                analysisHtml += `
                  <div class="final-recommendation">
                    <div class="recommendation-header caution-recommendation">
                      <i class="fas fa-exclamation-circle"></i>
                      <h4>Recommendation</h4>
                    </div>
                    <p>${contract.analysis.recommendation}</p>
                  </div>
                `
              } else {
                analysisHtml += `
                  <div class="error-message">
                    <p>Analysis data is incomplete. You may need to re-analyze this contract.</p>
                  </div>
                `
              }

              analysisContainer.innerHTML = analysisHtml
              document.querySelector(".contract-detail").appendChild(analysisContainer)

              attachContractButtonListeners()
            })
        } else if (contract.status === "analyzed") {
          const analysisContainer = document.createElement("div")
          analysisContainer.className = "contract-analysis"
          analysisContainer.innerHTML = `
            <h3 class="analysis-title"><i class="fas fa-chart-bar"></i> Contract Analysis</h3>
            <div class="error-message">
              <i class="fas fa-exclamation-circle"></i>
              <p>Analysis data is not available. You may need to re-analyze this contract.</p>
              <button class="analyze-btn" data-id="${contract.id}"><i class="fas fa-robot"></i> Re-analyze</button>
            </div>
          `

          html += analysisContainer.outerHTML
        }

        container.innerHTML = html

        attachContractButtonListeners()
      } else {
        container.innerHTML = `<div class="error-message">Error loading contract: ${data.error}</div>`
      }
    })
    .catch((error) => {
      console.error("Error loading contract:", error)
      container.innerHTML = `<div class="error-message">An error occurred while loading the contract. Please try again.</div>`
    })
}

function analyzeContract(contractId, button) {
  button.disabled = true
  button.innerHTML = `
      <div class="loading-indicator">
          <div class="spinner"></div>
      </div>
  `

  console.log("Analyzing contract ID:", contractId)

  fetch(`contract_api.php?action=analyze_contract&contract_id=${contractId}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `contract_id=${contractId}`,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`)
      }
      return response.json()
    })
    .then((data) => {
      console.log("Analysis response:", data)
      if (data.success) {
        displayContractAnalysis(contractId)
      } else {
        alert("Error analyzing contract: " + data.error)
      }

      button.disabled = false
      button.innerHTML = '<i class="fas fa-robot"></i> Analyze'
    })
    .catch((error) => {
      console.error("Error analyzing contract:", error)
      alert("An error occurred while analyzing the contract. Please try again.")

      button.disabled = false
      button.innerHTML = '<i class="fas fa-robot"></i> Analyze'
    })
}

function updateContractStatus(contractId, status) {
  console.log(`Updating contract ${contractId} to status: ${status}`)

  fetch(`contract_api.php?action=update_contract_status`, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `contract_id=${contractId}&status=${status}`,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`)
      }
      return response.json()
    })
    .then((data) => {
      if (data.success) {
        window.location.reload()
      } else {
        alert("Error updating contract status: " + data.error)
      }
    })
    .catch((error) => {
      console.error("Error updating contract status:", error)
      alert("An error occurred while updating the contract status. Please try again.")
    })
}

function viewComparison(comparisonId) {
  const modal = document.getElementById("comparison-details-modal")
  const container = document.getElementById("comparison-details-container")

  container.innerHTML = `
      <div class="loading-indicator">
          <div class="spinner"></div>
          <p>Loading comparison details...</p>
      </div>
  `

  modal.style.display = "block"

  fetch(`contract_api.php?action=get_comparison&comparison_id=${comparisonId}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const comparison = data.comparison

        let html = `
                  <div class="comparison-detail">
                      <h2>${comparison.title}</h2>
                      <p class="comparison-date"><i class="fas fa-calendar-alt"></i> Created: ${formatDate(comparison.created_at)}</p>
                      
                      ${comparison.description ? `<div class="comparison-description"><p>${comparison.description}</p></div>` : ""}
                      
                      <h3>Contracts Being Compared:</h3>
                      <div class="comparison-contracts">
                          <ul>
              `

        comparison.contracts.forEach((contract) => {
          html += `<li>${contract.title} <span class="contract-status ${contract.status}">${contract.status.charAt(0).toUpperCase() + contract.status.slice(1)}</span></li>`
        })

        html += `
                          </ul>
                      </div>
              `

        if (comparison.conclusion) {
          html += `
                      <div class="comparison-conclusion">
                          <h3>Comparison Results:</h3>
                          <div class="conclusion-content">
                              ${comparison.conclusion}
                          </div>
                      </div>
                  `
        } else {
          html += `
                      <div class="comparison-table-container">
                          <h3>Contract Comparison:</h3>
                          <table class="comparison-table">
                              <thead>
                                  <tr>
                                      <th>Feature</th>
                  `

          comparison.contracts.forEach((contract) => {
            html += `<th>${contract.title}</th>`
          })

          html += `
                                  </tr>
                              </thead>
                              <tbody>
                                  <tr>
                                      <td>Summary</td>
                  `

          comparison.contracts.forEach((contract) => {
            if (contract.analysis && contract.analysis.summary) {
              html += `<td>${contract.analysis.summary}</td>`
            } else {
              html += `<td>Not analyzed</td>`
            }
          })

          html += `
                                  </tr>
                                  <tr>
                                      <td>Recommendation</td>
                  `

          comparison.contracts.forEach((contract) => {
            if (contract.analysis && contract.analysis.recommendation) {
              html += `<td>${contract.analysis.recommendation}</td>`
            } else {
              html += `<td>Not analyzed</td>`
            }
          })

          html += `
                                  </tr>
                              </tbody>
                          </table>
                      </div>
                  `
        }

        html += `</div>`

        container.innerHTML = html
      } else {
        container.innerHTML = `<div class="error-message">Error loading comparison: ${data.error}</div>`
      }
    })
    .catch((error) => {
      console.error("Error loading comparison:", error)
      container.innerHTML = `<div class="error-message">An error occurred while loading the comparison. Please try again.</div>`
    })
}

function formatFileSize(bytes) {
  if (bytes === 0) return "0 Bytes"
  const k = 1024
  const sizes = ["Bytes", "KB", "MB", "GB", "TB"]
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Number.parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i]
}

function formatDate(dateString) {
  const date = new Date(dateString)
  const options = { year: "numeric", month: "long", day: "numeric", hour: "2-digit", minute: "2-digit" }
  return date.toLocaleDateString(undefined, options)
}

function attachContractButtonListeners() {
  document.querySelectorAll(".analyze-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const contractId = this.getAttribute("data-id")
      analyzeContract(contractId, this)
    })
  })

  document.querySelectorAll(".sign-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const contractId = this.getAttribute("data-id")
      updateContractStatus(contractId, "signed")
    })
  })

  document.querySelectorAll(".reject-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const contractId = this.getAttribute("data-id")
      updateContractStatus(contractId, "rejected")
    })
  })
}


document.addEventListener("DOMContentLoaded", () => {
  document.body.addEventListener("click", (e) => {
    if (
      e.target.classList.contains("analyze-btn") ||
      (e.target.parentElement && e.target.parentElement.classList.contains("analyze-btn"))
    ) {
      const button = e.target.classList.contains("analyze-btn") ? e.target : e.target.parentElement

      const contractId = button.getAttribute("data-id")
      if (contractId) {
        e.preventDefault()
        analyzeContractClickHandler(contractId, button)
      }
    }
  })

  const fileUploadLabels = document.querySelectorAll(".file-upload-label")
  fileUploadLabels.forEach((label) => {
    const text = label.innerHTML
    if (text.includes("Max size: 1MB")) {
      label.innerHTML = text.replace("Max size: 1MB", "Max size: 20MB")
    }
  })

  const contractUploadForm = document.getElementById("contract-upload-form")
  if (contractUploadForm) {
    contractUploadForm.addEventListener("submit", function (e) {
      e.preventDefault()

      const submitButton = this.querySelector('button[type="submit"]')
      const originalButtonText = submitButton.innerHTML
      submitButton.disabled = true
      submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...'

      const formData = new FormData(this)

      fetch("upload_contract.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`)
          }
          return response.json()
        })
        .then((data) => {
          if (data.success) {
            window.location.reload()
          } else {
            alert("Error uploading contract: " + data.error)
            submitButton.disabled = false
            submitButton.innerHTML = originalButtonText
          }
        })
        .catch((error) => {
          console.error("Error uploading contract:", error)
          alert("An error occurred while uploading the contract. Please try again.")
          submitButton.disabled = false
          submitButton.innerHTML = originalButtonText
        })
    })
  }
})

function analyzeContractClickHandler(contractId, button) {
  const originalContent = button.innerHTML

  button.disabled = true
  button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...'

  console.log("Analyzing contract ID:", contractId)

  fetch("contract_api.php?action=analyze_contract", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `contract_id=${contractId}`,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`)
      }
      return response.json()
    })
    .then((data) => {
      console.log("Analysis response:", data)
      if (data.success) {
        alert("Contract analyzed successfully!")

        window.location.reload()
      } else {
        alert("Error analyzing contract: " + data.error)
        button.disabled = false
        button.innerHTML = originalContent
      }
    })
    .catch((error) => {
      console.error("Error analyzing contract:", error)
      alert("An error occurred while analyzing the contract. Please try again.")

      button.disabled = false
      button.innerHTML = originalContent
    })
}

