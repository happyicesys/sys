import{h as c,T as i,g as f,f as v,a as r,u as _,w as t,F as w,o as g,Z as x,b as o,e as h,d as l}from"./app.a5ba100b.js";import{_ as V}from"./Authenticated.891b50c9.js";import{_ as n}from"./FormInput.52d22637.js";import"./open-closed.34e7965e.js";import"./use-resolve-button-type.ceb68aa2.js";import"./RectangleStackIcon.c2c6dc58.js";const b=o("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Account Settings ",-1),y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3 pt-5 md:pt-2"},S=["onSubmit"],U={class:"overflow-hidden shadow sm:rounded-md"},k={class:"bg-white px-4 py-5 sm:p-6"},F={class:"grid grid-cols-12 gap-6"},N={class:"col-span-12 sm:col-span-8"},T={class:"col-span-12 sm:col-span-6"},B={class:"col-span-12 sm:col-span-6"},E={class:"col-span-12 sm:col-span-6"},j={class:"col-span-12 sm:col-span-6"},A=o("div",{class:"bg-gray-50 px-4 py-3 text-right sm:px-6"},[o("button",{type:"submit",class:"inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"},"Save")],-1),O={__name:"Form",props:{user:Object},setup(u){const m=u;c(()=>{e.value=m.user?i({...d(),...m.user.data}):i(d())});const e=f(i(d()));function d(){return{id:"",name:"",username:"",email:"",password:"",password_confirmation:""}}function p(){e.value.clearErrors(),e.value.post("/self/"+e.value.id+"/update",{preserveState:!0,replace:!0})}return(C,s)=>(g(),v(w,null,[r(_(x),{title:"Account Settings"}),r(V,null,{header:t(()=>[b]),default:t(()=>[o("div",y,[o("form",{onSubmit:h(p,["prevent"])},[o("div",U,[o("div",k,[o("div",F,[o("div",N,[r(n,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=a=>e.value.name=a),error:e.value.errors.name,required:"true"},{default:t(()=>[l(" Name ")]),_:1},8,["modelValue","error"])]),o("div",T,[r(n,{modelValue:e.value.email,"onUpdate:modelValue":s[1]||(s[1]=a=>e.value.email=a),error:e.value.errors.email},{default:t(()=>[l(" Email ")]),_:1},8,["modelValue","error"])]),o("div",B,[r(n,{modelValue:e.value.username,"onUpdate:modelValue":s[2]||(s[2]=a=>e.value.username=a),error:e.value.errors.username},{default:t(()=>[l(" Username ")]),_:1},8,["modelValue","error"])]),o("div",E,[r(n,{modelValue:e.value.password,"onUpdate:modelValue":s[3]||(s[3]=a=>e.value.password=a),error:e.value.errors.password,placeholderStr:"Leave blank for same password",inputType:"password",autocomplete:"new-password"},{default:t(()=>[l(" Password ")]),_:1},8,["modelValue","error"])]),o("div",j,[r(n,{modelValue:e.value.password_confirmation,"onUpdate:modelValue":s[4]||(s[4]=a=>e.value.password_confirmation=a),error:e.value.errors.password_confirmation,placeholderStr:"Leave blank for same password",inputType:"password",autocomplete:"new-password-confirmation"},{default:t(()=>[l(" Password Confirmation ")]),_:1},8,["modelValue","error"])])])]),A])],40,S)])]),_:1})],64))}};export{O as default};
